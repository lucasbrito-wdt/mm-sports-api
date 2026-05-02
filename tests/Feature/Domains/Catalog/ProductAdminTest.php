<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Tracking\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->user = User::factory()->create();
    $this->user->assignRole('user');
});

it('rejeita listagem de admin sem autenticação', function () {
    $this->getJson('/api/admin/products')->assertUnauthorized();
});

it('rejeita utilizador comum', function () {
    $this->withHeaders(jwtHeaders($this->user))
        ->getJson('/api/admin/products')
        ->assertForbidden();
});

it('admin lista produtos incluindo rascunhos', function () {
    $category = Category::query()->create([
        'name' => 'Treino',
        'slug' => 'treino-'.Str::lower(Str::random(6)),
    ]);

    Product::query()->create([
        'title' => 'Borrador',
        'slug' => 'draft-'.Str::lower(Str::random(8)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
        'category_id' => $category->id,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);
    CommerceFixtures::publishedProductWithVariant('Publicado');

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/products?per_page=30');
    $res->assertOk();
    $titles = collect($res->json('data'))->pluck('title')->all();
    expect($titles)->toContain('Borrador', 'Publicado');

    $draft = collect($res->json('data'))->firstWhere('title', 'Borrador');
    expect($draft)->not->toBeNull();
    expect($draft['category_id'])->toBe((string) $category->id);
});

it('cria produto e regista audit log', function () {
    $slug = 'admin-novo-'.Str::lower(Str::random(6));
    $category = Category::query()->create([
        'name' => 'Categoria API',
        'slug' => 'categoria-api-'.Str::lower(Str::random(6)),
    ]);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/products', [
            'title' => 'Novo a partir de API',
            'slug' => $slug,
            'description' => null,
            'origin' => 'national',
            'allows_personalization' => false,
            'category_id' => $category->id,
            'size_chart_id' => null,
            'status' => 'draft',
            'ncm' => null,
            'meta_title' => null,
            'meta_description' => null,
        ]);
    $res->assertCreated();
    $id = $res->json('id');
    $res->assertJsonPath('category_id', $category->id);
    expect(Product::query()->where('slug', $slug)->exists())->toBeTrue();

    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $id)
            ->where('action', 'products.create')
            ->exists()
    )->toBeTrue();
});

it('atualiza produto e gera registo de audit', function () {
    $category = Category::query()->create([
        'name' => 'Acessórios',
        'slug' => 'acessorios-'.Str::lower(Str::random(6)),
    ]);

    $p = Product::query()->create([
        'title' => 'Editável',
        'slug' => 'edit-'.Str::lower(Str::random(6)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/products/{$p->id}", [
            'title' => 'Título corrigido',
            'status' => 'published',
            'category_id' => $category->id,
        ]);
    $res->assertOk();
    $p->refresh();
    expect($p->title)->toBe('Título corrigido');
    expect($p->status)->toBe(ProductStatus::Published);
    expect($p->category_id)->toBe($category->id);
    $res->assertJsonPath('category_id', $category->id);

    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $p->id)
            ->where('action', 'products.update')
            ->exists()
    )->toBeTrue();
});

it('apaga produto e regista audit', function () {
    $p = Product::query()->create([
        'title' => 'A apagar',
        'slug' => 'del-'.Str::lower(Str::random(6)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);
    $id = (string) $p->id;

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->deleteJson("/api/admin/products/{$id}");
    $res->assertNoContent();
    expect(Product::query()->find($id))->toBeNull();

    expect(
        AuditLog::query()
            ->where('auditable_id', $id)
            ->where('action', 'products.delete')
            ->exists()
    )->toBeTrue();
});

it('filtra produtos por categoria incluindo descendentes em árvore de 3 níveis', function () {
    $root = Category::query()->create([
        'name' => 'Esportes',
        'slug' => 'esportes-'.Str::lower(Str::random(6)),
    ]);
    $child = Category::query()->create([
        'name' => 'Futebol',
        'slug' => 'futebol-'.Str::lower(Str::random(6)),
        'parent_id' => $root->id,
    ]);
    $grandChild = Category::query()->create([
        'name' => 'Chuteiras',
        'slug' => 'chuteiras-'.Str::lower(Str::random(6)),
        'parent_id' => $child->id,
    ]);
    $outside = Category::query()->create([
        'name' => 'Basquete',
        'slug' => 'basquete-'.Str::lower(Str::random(6)),
    ]);

    $createProduct = function (string $title, string $slug, string $categoryId): void {
        Product::query()->create([
            'title' => $title,
            'slug' => $slug,
            'description' => null,
            'origin' => ProductOrigin::National,
            'allows_personalization' => false,
            'size_chart_id' => null,
            'status' => ProductStatus::Draft,
            'category_id' => $categoryId,
            'ncm' => null,
            'meta_title' => null,
            'meta_description' => null,
        ]);
    };

    $createProduct('Produto Raiz', 'produto-raiz-'.Str::lower(Str::random(6)), $root->id);
    $createProduct('Produto Filho', 'produto-filho-'.Str::lower(Str::random(6)), $child->id);
    $createProduct('Produto Neto', 'produto-neto-'.Str::lower(Str::random(6)), $grandChild->id);
    $createProduct('Produto Fora', 'produto-fora-'.Str::lower(Str::random(6)), $outside->id);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/products?per_page=30&filters[category_id]='.(string) $root->id);

    $res->assertOk();
    $titles = collect($res->json('data'))->pluck('title')->all();

    expect($titles)->toContain('Produto Raiz', 'Produto Filho', 'Produto Neto')
        ->and($titles)->not->toContain('Produto Fora');
});

it('mantém comportamento para categoria sem descendentes no filtro', function () {
    $leaf = Category::query()->create([
        'name' => 'Luvas',
        'slug' => 'luvas-'.Str::lower(Str::random(6)),
    ]);
    $other = Category::query()->create([
        'name' => 'Mochilas',
        'slug' => 'mochilas-'.Str::lower(Str::random(6)),
    ]);

    Product::query()->create([
        'title' => 'Produto Folha',
        'slug' => 'produto-folha-'.Str::lower(Str::random(6)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
        'category_id' => $leaf->id,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);
    Product::query()->create([
        'title' => 'Produto Outra Categoria',
        'slug' => 'produto-outra-'.Str::lower(Str::random(6)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
        'category_id' => $other->id,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/products?per_page=30&filters[category_id]='.(string) $leaf->id);

    $res->assertOk();
    $titles = collect($res->json('data'))->pluck('title')->all();

    expect($titles)->toBe(['Produto Folha']);
});

it('não entra em comportamento patológico com ciclo anômalo no filtro por categoria', function () {
    $categoryA = Category::query()->create([
        'name' => 'Categoria A',
        'slug' => 'categoria-a-'.Str::lower(Str::random(6)),
    ]);
    $categoryB = Category::query()->create([
        'name' => 'Categoria B',
        'slug' => 'categoria-b-'.Str::lower(Str::random(6)),
        'parent_id' => $categoryA->id,
    ]);
    $outside = Category::query()->create([
        'name' => 'Categoria Externa',
        'slug' => 'categoria-externa-'.Str::lower(Str::random(6)),
    ]);

    // Simula anomalia de dados (A -> B -> A), fora das validações da camada de serviço.
    Category::query()
        ->whereKey($categoryA->id)
        ->update(['parent_id' => $categoryB->id]);

    Product::query()->create([
        'title' => 'Produto Ciclo A',
        'slug' => 'produto-ciclo-a-'.Str::lower(Str::random(6)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
        'category_id' => $categoryA->id,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);
    Product::query()->create([
        'title' => 'Produto Ciclo B',
        'slug' => 'produto-ciclo-b-'.Str::lower(Str::random(6)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
        'category_id' => $categoryB->id,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);
    Product::query()->create([
        'title' => 'Produto Fora',
        'slug' => 'produto-fora-ciclo-'.Str::lower(Str::random(6)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
        'category_id' => $outside->id,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/products?per_page=30&filters[category_id]='.(string) $categoryA->id);

    $res->assertOk();
    $titles = collect($res->json('data'))->pluck('title')->all();

    expect($titles)->toContain('Produto Ciclo A', 'Produto Ciclo B')
        ->and($titles)->not->toContain('Produto Fora');
});

it('retorna 422 no create com category_id inválido ou inexistente', function () {
    $basePayload = [
        'title' => 'Produto inválido',
        'slug' => 'produto-invalido-'.Str::lower(Str::random(6)),
        'description' => null,
        'origin' => 'national',
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => 'draft',
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ];

    $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/products', array_merge($basePayload, [
            'category_id' => 'invalido',
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category_id']);

    $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/products', array_merge($basePayload, [
            'slug' => 'produto-invalido-'.Str::lower(Str::random(6)),
            'category_id' => (string) Str::ulid(),
        ]))
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category_id']);
});

it('retorna 422 no update com category_id inválido ou inexistente', function () {
    $product = Product::query()->create([
        'title' => 'Produto para update inválido',
        'slug' => 'produto-update-'.Str::lower(Str::random(6)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);

    $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/products/{$product->id}", [
            'category_id' => 'invalido',
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category_id']);

    $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/products/{$product->id}", [
            'category_id' => (string) Str::ulid(),
        ])
        ->assertStatus(422)
        ->assertJsonValidationErrors(['category_id']);
});
