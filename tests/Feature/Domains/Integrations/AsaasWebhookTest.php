<?php

use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use App\Domains\Tracking\Models\AnalyticsEvent;
use App\Domains\Tracking\Models\OrderStatusTransition;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

beforeEach(function () {
    config(['services.asaas.webhook_token' => 'test-wh-secret']);
});

it('confirma pagamento e ignora o mesmo evento no segundo POST', function () {
    $user = User::factory()->create();
    [, $v] = CommerceFixtures::publishedProductWithVariant();
    $orderRes = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/orders', [
            'items' => [
                ['product_variant_id' => (string) $v->id, 'quantity' => 1],
            ],
            'address' => ['postal_code' => '01310100', 'street' => 'Av. Paulista', 'number' => '1000', 'district' => 'Bela Vista', 'city' => 'São Paulo', 'state' => 'SP'], 'billing_type' => 'PIX',
        ]);
    $orderRes->assertCreated();
    $orderId = $orderRes->json('order_id');

    $externalPaymentId = 'asaas_evt_'.uniqid('', true);
    $payload = [
        'payment' => [
            'id' => $externalPaymentId,
            'status' => 'RECEIVED',
            'externalReference' => $orderId,
        ],
    ];
    $headers = ['X-Asaas-Token' => 'test-wh-secret'];

    $this->postJson('/api/webhooks/asaas', $payload, $headers)->assertOk()->assertJson(['ok' => true]);

    $order = Order::query()->findOrFail($orderId);
    expect($order->status)->toBe(OrderStatus::Paid);
    expect($order->paid_at)->not->toBeNull();
    $paidTransitions = OrderStatusTransition::query()
        ->where('order_id', $orderId)
        ->where('to_status', 'paid');
    expect($paidTransitions->count())->toBe(1);
    expect(AnalyticsEvent::query()->where('name', 'payment_confirmed')->where('user_id', $user->id)->count())->toBe(1);

    $this->postJson('/api/webhooks/asaas', $payload, $headers)->assertOk();

    expect(OrderStatusTransition::query()
        ->where('order_id', $orderId)
        ->where('to_status', 'paid')
        ->count())->toBe(1);
    expect(AnalyticsEvent::query()->where('name', 'payment_confirmed')->where('user_id', $user->id)->count())->toBe(1);
});

it('rejeita requisição sem id de pagamento', function () {
    $this->postJson('/api/webhooks/asaas', [], [
        'X-Asaas-Token' => 'test-wh-secret',
    ])->assertStatus(400);
});
