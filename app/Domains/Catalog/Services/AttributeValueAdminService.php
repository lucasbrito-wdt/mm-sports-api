<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AttributeValueAdminService
{
    public function __construct(
        private readonly AttributeValue $attributeValue,
    ) {}

    public function create(Attribute $attribute, array $data): AttributeValue
    {
        $payload = $this->valuePayload($data, includeSlugFromValue: true);
        $value = $attribute->values()->create($payload);
        $this->flushFacetRelatedCache();

        return $value;
    }

    public function updateModel(AttributeValue $value, array $data): AttributeValue
    {
        $payload = $this->valuePayload($data, includeSlugFromValue: array_key_exists('value', $data));
        if ($payload !== []) {
            $value->update($payload);
        }
        $this->flushFacetRelatedCache();

        return $value->refresh();
    }

    public function delete(AttributeValue $value): void
    {
        $value->delete();
        $this->flushFacetRelatedCache();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function valuePayload(array $data, bool $includeSlugFromValue): array
    {
        $keys = array_flip($this->attributeValue->getFillable());
        unset($keys['attribute_id']);
        $out = array_intersect_key($data, $keys);
        if ($includeSlugFromValue && isset($data['value']) && is_string($data['value'])) {
            $out['slug'] = Str::slug($data['value']);
        }

        return $out;
    }

    private function flushFacetRelatedCache(): void
    {
        $store = config('cache.default');
        if (in_array($store, ['redis', 'memcached'], true)) {
            Cache::tags(['facets'])->flush();
        }
    }
}
