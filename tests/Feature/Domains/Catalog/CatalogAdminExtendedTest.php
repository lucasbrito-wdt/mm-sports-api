<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Enums\PersonalizationOptionType;
use App\Domains\Tracking\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->user = User::factory()->create();
    $this->user->assignRole('user');
});

it('rejeita criação de tabela de medidas sem permissão admin', function () {
    $this->withHeaders(jwtHeaders($this->user))
        ->postJson('/api/admin/size_charts', [
            'name' => 'X',
            'table_json' => ['a' => 1],
        ])
        ->assertForbidden();
});

it('admin cria tabela de medidas e gera audit', function () {
    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/size_charts', [
            'name' => 'Tamanhos t-shirt',
            'table_json' => ['columns' => ['P', 'M'], 'rows' => []],
        ]);
    $res->assertCreated();
    $id = $res->json('id');
    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $id)
            ->where('action', 'size_charts.create')
            ->exists()
    )->toBeTrue();
});

it('admin lista tabelas de medidas', function () {
    $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/size_charts', [
            'name' => 'Grade A',
            'table_json' => ['rows' => []],
        ])
        ->assertCreated();

    $list = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/size_charts?per_page=20');
    $list->assertOk();
    expect(collect($list->json('data'))->pluck('name')->all())->toContain('Grade A');
});

it('admin cria e lista variante de produto', function () {
    [$product] = CommerceFixtures::publishedProductWithVariant();
    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson("/api/admin/products/{$product->id}/variants", [
            'sku' => 'VAR-API-1',
            'price' => 199.5,
            'compare_at_price' => null,
            'stock_quantity' => 3,
            'weight_grams' => 100,
            'length_cm' => null,
            'width_cm' => null,
            'height_cm' => null,
            'attribute_payload' => ['color' => 'red'],
            'is_active' => true,
        ]);
    $res->assertCreated();
    $variantId = $res->json('id');
    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $variantId)
            ->where('action', 'product_variants.create')
            ->exists()
    )->toBeTrue();

    $list = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson("/api/admin/products/{$product->id}/variants");
    $list->assertOk();
    $skus = collect($list->json('data'))->pluck('sku')->all();
    expect($skus)->toContain('VAR-API-1');
});

it('admin cria opção de personalização do produto', function () {
    [$product] = CommerceFixtures::publishedProductWithVariant();
    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson("/api/admin/products/{$product->id}/options", [
            'type' => PersonalizationOptionType::ShortText->value,
            'label' => 'Nome no verso',
            'is_required' => true,
            'additional_price' => 5,
            'max_length' => 20,
            'options_json' => null,
            'sort_order' => 1,
        ]);
    $res->assertCreated();
    $id = $res->json('id');
    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $id)
            ->where('action', 'product_personalization_options.create')
            ->exists()
    )->toBeTrue();
});
