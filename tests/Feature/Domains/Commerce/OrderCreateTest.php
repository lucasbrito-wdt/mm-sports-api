<?php

use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Enums\PersonalizationOptionType;
use App\Domains\Catalog\Models\ProductPersonalizationOption;
use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use App\Domains\Commerce\Models\OrderItem;
use App\Domains\Tracking\Models\AnalyticsEvent;
use App\Domains\Tracking\Models\OrderStatusTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

it('cria pedido pendente com rastreio e analytics', function () {
    $user = User::factory()->create();
    [, $v] = CommerceFixtures::publishedProductWithVariant();
    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/orders', [
            'items' => [
                ['product_variant_id' => (string) $v->id, 'quantity' => 1],
            ],
            'destination_postal_code' => '01310100',
        ]);
    $res->assertCreated();
    $id = $res->json('data.id');
    $order = Order::query()->findOrFail($id);
    expect($order->status)->toBe(OrderStatus::PendingPayment);
    expect($order->asaas_payment_id)->toStartWith('test_');
    expect(OrderStatusTransition::query()->where('order_id', $order->id)->count())->toBe(1);
    expect(AnalyticsEvent::query()->where('name', 'order_created')->where('user_id', $user->id)->count())->toBe(1);
});

it('grava snapshot de personalização nas linhas do pedido', function () {
    $user = User::factory()->create();
    [$p, $v] = CommerceFixtures::publishedProductWithVariant();
    $p->update(['allows_personalization' => true]);
    $opt = ProductPersonalizationOption::query()->create([
        'product_id' => $p->id,
        'type' => PersonalizationOptionType::ShortText,
        'label' => 'Número',
        'is_required' => true,
        'additional_price' => 5,
        'max_length' => 3,
        'options_json' => null,
        'sort_order' => 0,
    ]);
    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/orders', [
            'items' => [[
                'product_variant_id' => (string) $v->id,
                'quantity' => 1,
                'personalization' => [
                    ['option_id' => (string) $opt->id, 'value' => '10'],
                ],
            ]],
            'destination_postal_code' => '01310100',
        ]);
    $res->assertCreated();
    $orderId = $res->json('data.id');
    $row = OrderItem::query()->where('order_id', $orderId)->firstOrFail();
    expect($row->personalization_snapshot)->toBeArray()
        ->and($row->personalization_snapshot[0]['value'])->toBe('10')
        ->and($row->personalization_snapshot[0]['label'])->toBe('Número');
});
