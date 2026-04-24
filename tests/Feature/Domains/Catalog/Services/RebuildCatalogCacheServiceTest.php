<?php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\RebuildCatalogCacheService;
use Illuminate\Support\Facades\DB;

it('rehydrates attribute_value_ids cache on all products', function () {
    $a = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $v = AttributeValue::create(['attribute_id' => $a->id, 'value' => 'Nike', 'slug' => 'nike']);

    $p = Product::create([
        'title' => 'X',
        'slug' => 'rebuild-x',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
    DB::table('product_attribute_values')->insert([
        'product_id' => $p->id,
        'attribute_value_id' => $v->id,
    ]);

    $p->attribute_value_ids = [];
    $p->saveQuietly();

    expect($p->refresh()->attribute_value_ids)->toBe([]);

    app(RebuildCatalogCacheService::class)->handle();

    expect($p->refresh()->attribute_value_ids)->toBe([(string) $v->id]);
});
