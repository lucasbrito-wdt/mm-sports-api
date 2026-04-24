<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Tracking\Models\AnalyticsEvent;
use App\Domains\Tracking\Models\AuditLog;
use App\Domains\Tracking\Models\OrderStatusTransition;
use App\Domains\Tracking\Models\WebhookInbox;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('exige autenticação nas rotas admin de tracking', function () {
    $this->getJson('/api/admin/analytics-events')->assertUnauthorized();
    $this->getJson('/api/admin/audit-logs')->assertUnauthorized();
    $this->getJson('/api/admin/webhook-inbox')->assertUnauthorized();
    $this->getJson('/api/admin/order-status-transitions')->assertUnauthorized();
});

it('admin lista e mostra analytics events', function () {
    AnalyticsEvent::query()->create([
        'name' => 'page_view',
        'properties' => ['path' => '/'],
        'source' => 'storefront',
    ]);
    $list = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/analytics-events');
    $list->assertOk();
    expect($list->json('data'))->toBeArray()->not->toBeEmpty();
    $id = $list->json('data.0.id');
    $show = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/analytics-events/'.$id);
    $show->assertOk()->assertJsonPath('name', 'page_view');
});

it('admin lista e mostra audit logs', function () {
    AuditLog::query()->create([
        'actor_user_id' => $this->admin->id,
        'action' => 'test.action',
        'auditable_type' => 'banner',
        'auditable_id' => (string) \Illuminate\Support\Str::ulid(),
        'old_values' => null,
        'new_values' => ['a' => 1],
    ]);
    $list = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/audit-logs');
    $list->assertOk();
    expect($list->json('data'))->toBeArray()->not->toBeEmpty();
    $id = $list->json('data.0.id');
    $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/audit-logs/'.$id)
        ->assertOk()
        ->assertJsonPath('action', 'test.action');
});

it('admin lista webhook inbox', function () {
    WebhookInbox::query()->create([
        'provider' => 'asaas',
        'external_event_id' => 'evt_'.uniqid(),
        'processing_result' => 'ok',
    ]);
    $list = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/webhook-inbox');
    $list->assertOk();
    expect($list->json('data'))->toBeArray()->not->toBeEmpty();
    $id = $list->json('data.0.id');
    $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/webhook-inbox/'.$id)
        ->assertOk()
        ->assertJsonPath('provider', 'asaas');
});

it('admin lista transições de estado com filtro por pedido', function () {
    $user = User::factory()->create();
    [, $variant] = CommerceFixtures::publishedProductWithVariant();
    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/orders', [
            'items' => [
                ['product_variant_id' => (string) $variant->id, 'quantity' => 1],
            ],
            'destination_postal_code' => '01310100',
        ]);
    $res->assertCreated();
    $orderId = $res->json('data.id');
    $tid = OrderStatusTransition::query()->where('order_id', $orderId)->value('id');
    expect($tid)->not->toBeNull();

    $list = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/order-status-transitions?filters[order_id]='.$orderId);
    $list->assertOk();
    expect($list->json('data'))->toHaveCount(1);

    $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/order-status-transitions/'.$tid)
        ->assertOk()
        ->assertJsonPath('order_id', $orderId);
});
