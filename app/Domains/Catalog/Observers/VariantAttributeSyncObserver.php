<?php

namespace App\Domains\Catalog\Observers;

use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Support\Facades\DB;

class VariantAttributeSyncObserver
{
    /** Recalcula `attribute_payload` e `attribute_value_ids` a partir de `variant_attribute_values`. */
    public static function recomputeFromPivots(ProductVariant $variant): void
    {
        $rows = DB::table('variant_attribute_values as vav')
            ->join('attributes as a', 'a.id', '=', 'vav.attribute_id')
            ->join('attribute_values as av', 'av.id', '=', 'vav.attribute_value_id')
            ->where('vav.product_variant_id', $variant->id)
            ->orderBy('a.code')
            ->get(['a.code', 'av.value', 'av.id']);

        $payload = $rows->mapWithKeys(fn ($r) => [$r->code => $r->value])->all();
        $ids = $rows->pluck('id')->map(fn ($id) => (string) $id)->unique()->sort()->values()->all();

        $variant->attribute_payload = $payload;
        $variant->attribute_value_ids = $ids;
        $variant->saveQuietly();
    }
}
