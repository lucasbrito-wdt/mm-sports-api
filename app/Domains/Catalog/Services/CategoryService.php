<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Database\QueryException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CategoryService extends BaseService
{
    public function __construct(
        private readonly Category $category,
        private readonly Product $product,
    ) {
        $this->setModel($this->category);
    }

    public function create(array $data): Category
    {
        return $this->store($data);
    }

    public function store(array $data)
    {
        $payload = $this->onlyFillable($data);

        $this->assertParentIsValid($payload['parent_id'] ?? null);

        /** @var Category $category */
        $category = $this->category->newQuery()->create($payload);

        return $category->refresh();
    }

    public function update(array $data, string $id)
    {
        /** @var Category $category */
        $category = $this->category->newQuery()->findOrFail($id);
        $payload = $this->onlyFillable($data);

        if (array_key_exists('parent_id', $payload)) {
            $this->assertParentIsValid($payload['parent_id'], $category->id);
        }

        $category->update($payload);

        return $category->refresh();
    }

    public function delete(string $id): void
    {
        $this->destroy($id);
    }

    public function destroy($id)
    {
        /** @var Category $category */
        $category = $this->category->newQuery()->findOrFail($id);
        try {
            DB::transaction(function () use ($category): void {
                $hasChildren = $this->category->newQuery()->where('parent_id', $category->id)->exists();
                if ($hasChildren) {
                    throw ValidationException::withMessages([
                        'category' => ['Não é possível remover a categoria porque existem subcategorias vinculadas.'],
                    ]);
                }

                $hasProducts = $this->product->newQuery()->where('category_id', $category->id)->exists();
                if ($hasProducts) {
                    throw ValidationException::withMessages([
                        'category' => ['Não é possível remover a categoria porque existem produtos vinculados.'],
                    ]);
                }

                $category->delete();
            });
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'category' => ['Não é possível remover a categoria porque ela está vinculada a outros registros.'],
            ]);
        }
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function buildTree(?Collection $categories = null): array
    {
        $categories ??= $this->category->newQuery()
            ->orderBy('display_order')
            ->orderBy('name')
            ->get();

        $grouped = $categories->groupBy('parent_id');

        $build = function ($parentId) use (&$build, $grouped): array {
            return $grouped->get($parentId, collect())
                ->map(function (Category $category) use (&$build): array {
                    return [
                        'id' => $category->id,
                        'name' => $category->name,
                        'slug' => $category->slug,
                        'parent_id' => $category->parent_id,
                        'is_active' => (bool) $category->is_active,
                        'display_order' => (int) $category->display_order,
                        'children' => $build($category->id),
                    ];
                })
                ->values()
                ->all();
        };

        return $build(null);
    }

    /**
     * @return array<int, string>
     */
    public function getDescendantIds(string $categoryId): array
    {
        $descendants = [];
        $queue = [$categoryId];
        $visited = [$categoryId => true];

        while ($queue !== []) {
            $parentIds = $queue;
            $queue = [];

            $children = $this->category->newQuery()
                ->whereIn('parent_id', $parentIds)
                ->pluck('id')
                ->all();

            foreach ($children as $childId) {
                if (isset($visited[$childId])) {
                    continue;
                }

                $visited[$childId] = true;
                $descendants[] = $childId;
                $queue[] = $childId;
            }
        }

        return $descendants;
    }

    private function assertParentIsValid(?string $parentId, ?string $selfId = null): void
    {
        if ($parentId === null || $parentId === '') {
            return;
        }

        if ($selfId !== null && $parentId === $selfId) {
            throw ValidationException::withMessages([
                'parent_id' => ['A categoria não pode ser filha dela mesma.'],
            ]);
        }

        $parentExists = $this->category->newQuery()->whereKey($parentId)->exists();
        if (! $parentExists) {
            throw ValidationException::withMessages([
                'parent_id' => ['A categoria pai informada não existe.'],
            ]);
        }

        if ($selfId !== null) {
            $descendants = $this->getDescendantIds($selfId);
            if (in_array($parentId, $descendants, true)) {
                throw ValidationException::withMessages([
                    'parent_id' => ['Não é permitido criar ciclo na hierarquia de categorias.'],
                ]);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyFillable(array $data): array
    {
        return array_intersect_key($data, array_flip($this->category->getFillable()));
    }
}
