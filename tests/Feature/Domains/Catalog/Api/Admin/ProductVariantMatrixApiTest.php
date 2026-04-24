<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('generates 2x2 variant matrix via admin API', function () {
    $color = Attribute::create(['code' => 'color', 'label' => 'Cor', 'type' => AttributeType::Variant, 'input_type' => AttributeInputType::Swatch]);
    $size = Attribute::create(['code' => 'size', 'label' => 'Tamanho', 'type' => AttributeType::Variant, 'input_type' => AttributeInputType::Select]);
    $azul = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Azul', 'slug' => 'azul']);
    $verm = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Vermelho', 'slug' => 'vermelho']);
    $p = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'P', 'slug' => 'p']);
    $m = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M', 'slug' => 'm']);

    $product = Product::create([
        'title' => 'Camisa',
        'slug' => 'api-mat',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);

    $this->withHeaders(jwtHeaders($this->admin))
        ->postJson("/api/admin/products/{$product->id}/variant-matrix", [
            'axes' => [
                ['attribute_id' => $color->id, 'value_ids' => [$azul->id, $verm->id]],
                ['attribute_id' => $size->id, 'value_ids' => [$p->id, $m->id]],
            ],
        ])
        ->assertOk();

    expect($product->refresh()->variants()->count())->toBe(4);
});
