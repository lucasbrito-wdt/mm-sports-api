<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('runs full CRUD flow for product images', function () {
    $product = Product::create([
        'title' => 'P', 'slug' => 'p-api-img-' . Str::random(6),
        'origin' => 'national', 'status' => 'draft',
    ]);

    // Create
    $created = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson("/api/admin/products/{$product->id}/images", [
            'url' => 'https://cdn.test/abc.jpg',
            'alt' => 'frente',
        ])
        ->assertCreated()
        ->json('data');

    // List
    $this->withHeaders(jwtHeaders($this->admin))
        ->getJson("/api/admin/products/{$product->id}/images")
        ->assertOk()
        ->assertJsonCount(1, 'data');

    // Update
    $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/products/{$product->id}/images/{$created['id']}", [
            'alt' => 'frente atualizada',
        ])
        ->assertOk()
        ->assertJsonPath('data.alt', 'frente atualizada');

    // Delete
    $this->withHeaders(jwtHeaders($this->admin))
        ->deleteJson("/api/admin/products/{$product->id}/images/{$created['id']}")
        ->assertNoContent();

    expect(ProductImage::where('product_id', $product->id)->count())->toBe(0);
});
