<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Attribute;
use Illuminate\Support\Facades\Cache;

class AttributeAdminService
{
    public function __construct(
        private readonly Attribute $attribute,
    ) {}

    public function create(array $data): Attribute
    {
        $attr = $this->attribute->newQuery()->create($this->onlyFillable($data));
        $this->flushFacetRelatedCache();

        return $attr;
    }

    public function updateModel(Attribute $attribute, array $data): Attribute
    {
        $attribute->update($this->onlyFillable($data));
        $this->flushFacetRelatedCache();

        return $attribute->refresh();
    }

    public function delete(Attribute $attribute): void
    {
        $attribute->delete();
        $this->flushFacetRelatedCache();
    }

    private function flushFacetRelatedCache(): void
    {
        $store = config('cache.default');
        if (in_array($store, ['redis', 'memcached'], true)) {
            Cache::tags(['facets'])->flush();
        }
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function onlyFillable(array $data): array
    {
        return array_intersect_key($data, array_flip($this->attribute->getFillable()));
    }
}
