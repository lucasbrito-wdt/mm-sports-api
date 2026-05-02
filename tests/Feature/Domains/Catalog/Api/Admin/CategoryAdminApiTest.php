<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

function categoriesAdminApiMigrationInstance(): object
{
    /** @var object $migration */
    $migration = require base_path('app/Domains/Catalog/Migrations/2026_04_25_141800_create_categories_and_link_products.php');

    return $migration;
}

beforeEach(function () {
    if (! Schema::hasTable('categories') || ! Schema::hasColumn('products', 'category_id')) {
        $migration = categoriesAdminApiMigrationInstance();
        call_user_func([$migration, 'up']);
    }

    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->user = User::factory()->create();
    $this->user->assignRole('user');
});

it('rejeita acesso sem autenticação', function () {
    $this->getJson('/api/admin/categories/tree')->assertUnauthorized();
});

it('rejeita utilizador sem permissão de admin', function () {
    $this->withHeaders(jwtHeaders($this->user))
        ->getJson('/api/admin/categories')
        ->assertForbidden();
});

it('retorna 403 para store, update e destroy sem permissão de categorias', function () {
    $category = Category::create([
        'name' => 'Categoria Restrita',
        'slug' => 'categoria-restrita-'.Str::lower(Str::random(6)),
    ]);

    $this->withHeaders(jwtHeaders($this->user))
        ->postJson('/api/admin/categories', [
            'name' => 'Tentativa sem permissão',
            'slug' => 'tentativa-sem-permissao-'.Str::lower(Str::random(6)),
        ])
        ->assertForbidden();

    $this->withHeaders(jwtHeaders($this->user))
        ->putJson("/api/admin/categories/{$category->id}", [
            'name' => 'Não pode editar',
        ])
        ->assertForbidden();

    $this->withHeaders(jwtHeaders($this->user))
        ->deleteJson("/api/admin/categories/{$category->id}")
        ->assertForbidden();
});

it('admin executa CRUD de categorias e consulta árvore', function () {
    $rootSlug = 'esportes-'.Str::lower(Str::random(6));
    $rootResponse = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/categories', [
            'name' => 'Esportes',
            'slug' => $rootSlug,
            'is_active' => true,
            'display_order' => 1,
        ]);

    $rootResponse->assertCreated()
        ->assertJsonPath('data.name', 'Esportes')
        ->assertJsonPath('data.parent_id', null);

    $rootId = (string) $rootResponse->json('data.id');

    $childSlug = 'futebol-'.Str::lower(Str::random(6));
    $childResponse = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/categories', [
            'name' => 'Futebol',
            'slug' => $childSlug,
            'parent_id' => $rootId,
            'is_active' => true,
            'display_order' => 2,
        ]);

    $childResponse->assertCreated()
        ->assertJsonPath('data.parent_id', $rootId);

    $childId = (string) $childResponse->json('data.id');

    $listResponse = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/categories');

    $listResponse->assertOk();
    $listIds = collect($listResponse->json('data'))->pluck('id')->all();
    expect($listIds)->toContain($rootId, $childId);

    $treeResponse = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/categories/tree');

    $treeResponse->assertOk()
        ->assertJsonPath('data.0.id', $rootId)
        ->assertJsonPath('data.0.children.0.id', $childId);

    $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/categories/{$childId}", [
            'name' => 'Futebol Profissional',
            'is_active' => false,
            'display_order' => 7,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Futebol Profissional')
        ->assertJsonPath('data.is_active', false)
        ->assertJsonPath('data.display_order', 7);

    $this->withHeaders(jwtHeaders($this->admin))
        ->deleteJson("/api/admin/categories/{$childId}")
        ->assertNoContent();

    expect(Category::query()->find($childId))->toBeNull();
});

it('retorna 422 ao tentar criar ciclo indireto na hierarquia via API', function () {
    $root = Category::create([
        'name' => 'Raiz',
        'slug' => 'raiz-'.Str::lower(Str::random(6)),
    ]);
    $child = Category::create([
        'name' => 'Filha',
        'slug' => 'filha-'.Str::lower(Str::random(6)),
        'parent_id' => $root->id,
    ]);
    $grandChild = Category::create([
        'name' => 'Neta',
        'slug' => 'neta-'.Str::lower(Str::random(6)),
        'parent_id' => $child->id,
    ]);

    $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/categories/{$root->id}", [
            'parent_id' => (string) $grandChild->id,
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['parent_id']);
});
