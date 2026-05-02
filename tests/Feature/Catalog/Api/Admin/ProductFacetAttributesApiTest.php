<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('sync replaces facet value associations', function () {
    $brand = Attribute::create([
        'code' => 'brand-' . Str::random(4), 'label' => 'Marca',
        'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select,
    ]);
    $nike   = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike',   'slug' => 'nike-'   . Str::random(4)]);
    $adidas = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Adidas', 'slug' => 'adidas-' . Str::random(4)]);
    $product = Product::create(['title' => 'P', 'slug' => 'p-facet-' . Str::random(6), 'origin' => 'national', 'status' => 'draft']);

    // GET empty
    $this->withHeaders(jwtHeaders($this->admin))
        ->getJson("/api/admin/products/{$product->id}/facet-attributes")
        ->assertOk()
        ->assertJsonPath('data.value_ids', []);

    // Sync Nike
    $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/products/{$product->id}/facet-attributes", ['value_ids' => [$nike->id]])
        ->assertOk();

    expect(
        DB::table('product_attribute_values')->where('product_id', $product->id)->pluck('attribute_value_id')->map(fn ($x) => (string) $x)->all()
    )->toBe([$nike->id]);

    // Sync Adidas replaces Nike
    $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/products/{$product->id}/facet-attributes", ['value_ids' => [$adidas->id]])
        ->assertOk();

    expect(
        DB::table('product_attribute_values')->where('product_id', $product->id)->pluck('attribute_value_id')->map(fn ($x) => (string) $x)->all()
    )->toBe([$adidas->id]);
});
