<?php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\CatalogFacetService;

it('returns filterable attributes with counts', function () {
    $brand = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $nike = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike']);

    $p = Product::create([
        'title' => 'X',
        'slug' => 'x-facet',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
    $p->syncAttributeValues([$nike->id]);

    $facets = app(CatalogFacetService::class)->getFacets();

    $brandFacet = collect($facets)->firstWhere('code', 'brand');
    expect($brandFacet)->not->toBeNull();
    expect($brandFacet['values'][0]['slug'])->toBe('nike');
    expect($brandFacet['values'][0]['count'])->toBe(1);
});
