<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function productCategoryContractMigrationInstance(): object
{
    /** @var object $migration */
    $migration = require base_path('app/Domains/Catalog/Migrations/2026_04_25_141800_create_categories_and_link_products.php');

    return $migration;
}

beforeEach(function () {
    if (! Schema::hasTable('categories') || ! Schema::hasColumn('products', 'category_id')) {
        $migration = productCategoryContractMigrationInstance();
        call_user_func([$migration, 'up']);
    }

    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('aceita e retorna category_id no create de produto admin', function () {
    $category = Category::create([
        'name' => 'Corrida',
        'slug' => 'corrida-'.Str::lower(Str::random(6)),
    ]);

    $slug = 'tenis-'.Str::lower(Str::random(6));
    $response = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/products', [
            'title' => 'Tênis de Corrida',
            'slug' => $slug,
            'origin' => 'national',
            'allows_personalization' => false,
            'size_chart_id' => null,
            'category_id' => (string) $category->id,
            'status' => 'draft',
            'ncm' => null,
            'meta_title' => null,
            'meta_description' => null,
        ]);

    $response->assertCreated()
        ->assertJsonPath('category_id', (string) $category->id);

    $productId = (string) $response->json('id');
    $this->withHeaders(jwtHeaders($this->admin))
        ->getJson("/api/admin/products/{$productId}")
        ->assertOk()
        ->assertJsonPath('category_id', (string) $category->id);
});

it('aceita atualizar category_id no update de produto admin', function () {
    $currentCategory = Category::create([
        'name' => 'Basquete',
        'slug' => 'basquete-'.Str::lower(Str::random(6)),
    ]);
    $newCategory = Category::create([
        'name' => 'Vôlei',
        'slug' => 'volei-'.Str::lower(Str::random(6)),
    ]);

    $product = Product::create([
        'title' => 'Bola',
        'slug' => 'bola-'.Str::lower(Str::random(6)),
        'origin' => 'national',
        'status' => 'draft',
        'category_id' => $currentCategory->id,
    ]);

    $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/products/{$product->id}", [
            'category_id' => (string) $newCategory->id,
        ])
        ->assertOk()
        ->assertJsonPath('category_id', (string) $newCategory->id);

    $product->refresh();
    expect((string) $product->category_id)->toBe((string) $newCategory->id);
});
