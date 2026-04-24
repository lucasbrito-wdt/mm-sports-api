<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Marketing\Models\Promotion;
use App\Domains\Tracking\Models\AuditLog;
use Carbon\Carbon;
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

it('rejeita create de promoção sem autenticação', function () {
    $this->postJson('/api/admin/promotions', [])->assertUnauthorized();
});

it('rejeita user comum', function () {
    $this->withHeaders(jwtHeaders($this->user))
        ->getJson('/api/admin/promotions')
        ->assertForbidden();
});

it('admin cria promoção com items e gera audit', function () {
    [$product] = CommerceFixtures::publishedProductWithVariant();
    $starts = Carbon::now()->subDay();
    $ends = Carbon::now()->addMonth();

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/promotions', [
            'name' => 'Black',
            'type' => 'percent',
            'value' => 10,
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
            'is_active' => true,
            'min_order_total' => null,
            'items' => [
                ['product_id' => (string) $product->id, 'product_variant_id' => null],
            ],
        ]);
    $res->assertCreated();
    $id = $res->json('id');
    $promo = Promotion::query()->with('items')->findOrFail($id);
    expect($promo->items)->toHaveCount(1);
    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $id)
            ->where('action', 'promotions.create')
            ->exists()
    )->toBeTrue();
});

it('admin atualiza items da promoção', function () {
    [$p1] = CommerceFixtures::publishedProductWithVariant('P1');
    [$p2] = CommerceFixtures::publishedProductWithVariant('P2');
    $starts = Carbon::now()->subDay();
    $ends = Carbon::now()->addMonth();
    $create = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/promotions', [
            'name' => 'A',
            'type' => 'fixed_amount',
            'value' => 5,
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
            'items' => [
                ['product_id' => (string) $p1->id, 'product_variant_id' => null],
            ],
        ]);
    $id = $create->json('id');
    $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/promotions/{$id}", [
            'name' => 'A2',
            'items' => [
                ['product_id' => (string) $p2->id, 'product_variant_id' => null],
            ],
        ])
        ->assertOk();
    $promo = Promotion::query()->with('items')->findOrFail($id);
    expect($promo->name)->toBe('A2');
    expect($promo->items->pluck('product_id')->map(fn ($x) => (string) $x)->all())
        ->toContain((string) $p2->id);
    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $id)
            ->where('action', 'promotions.update')
            ->exists()
    )->toBeTrue();
});

it('admin remove promoção e regista delete', function () {
    [$product] = CommerceFixtures::publishedProductWithVariant();
    $starts = Carbon::now()->subDay();
    $ends = Carbon::now()->addMonth();
    $id = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/promotions', [
            'name' => 'X',
            'type' => 'percent',
            'value' => 5,
            'starts_at' => $starts->toIso8601String(),
            'ends_at' => $ends->toIso8601String(),
            'items' => [
                ['product_id' => (string) $product->id, 'product_variant_id' => null],
            ],
        ])->json('id');

    $this->withHeaders(jwtHeaders($this->admin))
        ->deleteJson("/api/admin/promotions/{$id}")
        ->assertNoContent();
    expect(Promotion::query()->find($id))->toBeNull();
    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $id)
            ->where('action', 'promotions.delete')
            ->exists()
    )->toBeTrue();
});
