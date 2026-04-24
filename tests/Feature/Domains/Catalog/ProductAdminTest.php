<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
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
    Product::query()->create([
        'title' => 'Borrador',
        'slug' => 'draft-'.Str::lower(Str::random(8)),
        'description' => null,
        'origin' => ProductOrigin::National,
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => ProductStatus::Draft,
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
});

it('cria produto e regista audit log', function () {
    $slug = 'admin-novo-'.Str::lower(Str::random(6));
    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/products', [
            'title' => 'Novo a partir de API',
            'slug' => $slug,
            'description' => null,
            'origin' => 'national',
            'allows_personalization' => false,
            'size_chart_id' => null,
            'status' => 'draft',
            'ncm' => null,
            'meta_title' => null,
            'meta_description' => null,
        ]);
    $res->assertCreated();
    $id = $res->json('id');
    expect(Product::query()->where('slug', $slug)->exists())->toBeTrue();

    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $id)
            ->where('action', 'products.create')
            ->exists()
    )->toBeTrue();
});

it('atualiza produto e gera registo de audit', function () {
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
        ]);
    $res->assertOk();
    $p->refresh();
    expect($p->title)->toBe('Título corrigido');
    expect($p->status)->toBe(ProductStatus::Published);

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
