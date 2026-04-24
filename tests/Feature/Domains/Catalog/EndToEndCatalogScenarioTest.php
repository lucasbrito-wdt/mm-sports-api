<?php

use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Seeders\AttributeSeeder;
use App\Domains\Catalog\Seeders\AttributeValueSeeder;
use App\Domains\Catalog\Services\GenerateVariantMatrixService;

it('creates product, attaches facets, generates matrix, lists with filter', function () {
    (new AttributeSeeder)->run();
    (new AttributeValueSeeder)->run();

    $brand = Attribute::where('code', 'brand')->firstOrFail();
    $nike = $brand->values()->where('slug', 'nike')->firstOrFail();
    $team = Attribute::where('code', 'team')->firstOrFail();
    $cor = $team->values()->where('slug', 'corinthians')->firstOrFail();
    $color = Attribute::where('code', 'color')->firstOrFail();
    $azul = $color->values()->where('slug', 'azul')->firstOrFail();
    $size = Attribute::where('code', 'size')->firstOrFail();
    $m = $size->values()->where('slug', 'm')->firstOrFail();
    $g = $size->values()->where('slug', 'g')->firstOrFail();

    $product = Product::create([
        'title' => 'Camisa Corinthians Nike 2026',
        'slug' => 'camisa-cor-nike-2026',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);

    $product->syncAttributeValues([$nike->id, $cor->id]);

    app(GenerateVariantMatrixService::class)->handle($product, [
        (string) $color->id => [(string) $azul->id],
        (string) $size->id => [(string) $m->id, (string) $g->id],
    ]);

    $product->variants()->update([
        'is_active' => true,
        'stock_quantity' => 10,
        'price' => 199.90,
    ]);

    $this->getJson('/api/catalog/products?brand=nike&team=corinthians&color=azul&size=m')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'camisa-cor-nike-2026']);

    $this->getJson('/api/catalog/facets')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'nike', 'count' => 1]);
});
