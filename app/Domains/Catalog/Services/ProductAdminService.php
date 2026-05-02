<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Product;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class ProductAdminService extends BaseService
{
    public function __construct(
        private readonly Product $product,
        private readonly AuditLogger $auditLogger,
    ) {
        $this->setModel($this->product);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null)
    {
        $enrich = function ($query) {
            $query
                ->addSelect([
                    'cover_image_url' => \App\Domains\Catalog\Models\ProductImage::select('url')
                        ->whereColumn('product_id', 'products.id')
                        ->orderBy('display_order')
                        ->limit(1),
                ])
                ->with(['variants', 'personalizationOptions']);
        };
        if ($builderCallback) {
            $cb = $builderCallback;
            $enrich = function ($query) use ($enrich, $cb) {
                $enrich($query);
                $cb($query);
            };
        }
        if (empty($options['sort_by'])) {
            $options['sort_by'] = 'title';
            $options['sort_order'] = 'asc';
        }

        return parent::index($options, $enrich);
    }

    protected function applyFilters(Builder $query, array $options): Builder
    {
        $categoryFilter = $options['filters']['category_id'] ?? null;
        if (isset($options['filters']) && is_array($options['filters'])) {
            unset($options['filters']['category_id']);
        }

        $query = parent::applyFilters($query, $options);

        if (! is_string($categoryFilter) || trim($categoryFilter) === '') {
            return $query;
        }

        $categoryId = trim($categoryFilter);

        return $query->whereRaw(
            $this->categoryTreeSql(),
            [$categoryId]
        );
    }

    private function categoryTreeSql(): string
    {
        if (DB::getDriverName() === 'pgsql') {
            return <<<'SQL'
            category_id IN (
                WITH RECURSIVE category_tree AS (
                    SELECT id, ARRAY[id] AS path
                    FROM categories
                    WHERE id = ?
                    UNION ALL
                    SELECT c.id, ct.path || c.id
                    FROM categories c
                    INNER JOIN category_tree ct ON c.parent_id = ct.id
                    WHERE NOT (c.id = ANY(ct.path))
                )
                SELECT id FROM category_tree
            )
            SQL;
        }

        return <<<'SQL'
        category_id IN (
            WITH RECURSIVE category_tree(id, path) AS (
                SELECT id, id
                FROM categories
                WHERE id = ?
                UNION ALL
                SELECT c.id, category_tree.path || ',' || c.id
                FROM categories c
                INNER JOIN category_tree ON c.parent_id = category_tree.id
                WHERE instr(',' || category_tree.path || ',', ',' || c.id || ',') = 0
            )
            SELECT id FROM category_tree
        )
        SQL;
    }

    public function show(string $id)
    {
        $product = $this->findById($id);

        return $product->load(['variants', 'personalizationOptions']);
    }

    public function store(array $data)
    {
        return DB::transaction(function () use ($data) {
            $data = $this->onlyFillable($data);
            /** @var Product $model */
            $model = $this->getModel()->newQuery()->create($data);
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'products.create',
                $model,
                null,
                $this->auditSnapshot($model),
                request()
            );

            return $model->load(['variants', 'personalizationOptions']);
        });
    }

    public function update(array $data, string $id)
    {
        return DB::transaction(function () use ($data, $id) {
            /** @var Product $record */
            $record = $this->findById($id);
            $old = $this->auditSnapshot($record);
            $data = $this->onlyFillable($data);
            $record->update($data);
            $record->refresh();
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'products.update',
                $record,
                $old,
                $this->auditSnapshot($record),
                request()
            );

            return $record->load(['variants', 'personalizationOptions']);
        });
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $record = $this->findById($id);
            $old = $this->auditSnapshot($record);
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'products.delete',
                $record,
                $old,
                null,
                request()
            );

            return $record->delete();
        });
    }

    private function onlyFillable(array $data): array
    {
        $keys = $this->getModel()->getFillable();

        return array_intersect_key($data, array_flip($keys));
    }

    private function auditSnapshot(Model $model): array
    {
        $a = $model->getAttributes();
        if (isset($a['origin']) && $a['origin'] instanceof \UnitEnum) {
            $a['origin'] = $a['origin'] instanceof \BackedEnum ? $a['origin']->value : (string) $a['origin'];
        }
        if (isset($a['status']) && $a['status'] instanceof \UnitEnum) {
            $a['status'] = $a['status'] instanceof \BackedEnum ? $a['status']->value : (string) $a['status'];
        }

        return $a;
    }
}
