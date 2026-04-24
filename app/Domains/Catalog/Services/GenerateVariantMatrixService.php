<?php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateVariantMatrixService extends BaseService
{
    public function __construct(
        private readonly ProductVariant $productVariant,
    ) {
        $this->setModel($this->productVariant);
    }

    /**
     * @param  array<string, string[]>  $valuesByAttribute  attribute_id => attribute_value_id[]
     */
    public function handle(Product $product, array $valuesByAttribute): void
    {
        DB::transaction(function () use ($product, $valuesByAttribute) {
            $this->syncVariantAxes($product, array_keys($valuesByAttribute));

            $combinations = $this->cartesian($valuesByAttribute);
            if ($combinations === []) {
                return;
            }

            $attrCodeById = Attribute::query()
                ->whereIn('id', array_keys($valuesByAttribute))
                ->pluck('code', 'id');

            $allValueIds = array_merge(...array_values($valuesByAttribute));
            $valuesIndex = AttributeValue::query()
                ->whereIn('id', $allValueIds)
                ->get()
                ->keyBy('id');

            foreach ($combinations as $combo) {
                $signatureIds = array_map('strval', array_values($combo));
                sort($signatureIds);
                $existing = $this->findVariantBySignature($product, $signatureIds);
                if ($existing !== null) {
                    continue;
                }

                $payload = [];
                foreach ($combo as $attributeId => $valueId) {
                    $payload[$attrCodeById[$attributeId]] = $valuesIndex[$valueId]->value;
                }

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $this->makeSku($product, $payload),
                    'price' => 0,
                    'stock_quantity' => 0,
                    'attribute_payload' => $payload,
                    'is_active' => false,
                ]);

                $pivotRows = [];
                foreach ($combo as $attributeId => $valueId) {
                    $pivotRows[] = [
                        'product_variant_id' => $variant->id,
                        'attribute_id' => $attributeId,
                        'attribute_value_id' => $valueId,
                    ];
                }
                DB::table('variant_attribute_values')->insert($pivotRows);

                $variant->attribute_value_ids = array_values($combo);
                $variant->save();
            }
        });
    }

    /**
     * @param  string[]  $attributeIds
     */
    private function syncVariantAxes(Product $product, array $attributeIds): void
    {
        $sync = [];
        foreach (array_values($attributeIds) as $i => $id) {
            $sync[$id] = ['display_order' => $i];
        }
        $product->variantAxes()->sync($sync);
    }

    /**
     * @param  array<string, string[]>  $values
     * @return array<int, array<string, string>>
     */
    private function cartesian(array $values): array
    {
        $result = [[]];
        foreach ($values as $attrId => $vids) {
            $next = [];
            foreach ($result as $row) {
                foreach ($vids as $vid) {
                    $next[] = $row + [$attrId => $vid];
                }
            }
            $result = $next;
        }

        return $result;
    }

    /**
     * @param  string[]  $sortedValueIds
     */
    private function findVariantBySignature(Product $product, array $sortedValueIds): ?ProductVariant
    {
        foreach ($product->variants()->with('attributeValues')->get() as $variant) {
            $existing = $variant->attributeValues->pluck('id')->map(fn ($id) => (string) $id)->sort()->values()->all();
            if ($existing === $sortedValueIds) {
                return $variant;
            }
        }

        return null;
    }

    /**
     * @param  array<string, string>  $payload
     */
    private function makeSku(Product $product, array $payload): string
    {
        $parts = [Str::upper(Str::slug($product->title)), ...array_map(
            fn ($v) => Str::upper(Str::slug((string) $v)),
            array_values($payload)
        )];

        return implode('-', $parts).'-'.Str::upper(Str::random(4));
    }
}
