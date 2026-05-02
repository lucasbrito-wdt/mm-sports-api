<?php

use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

test('POST /api/guest-orders returns 422 for missing fields', function () {
    $response = $this->postJson('/api/guest-orders', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['billing_type', 'customer', 'items']);
});

test('GET /api/orders/{id}/status returns 404 for unknown order', function () {
    $response = $this->getJson('/api/orders/nonexistent-id/status');

    $response->assertStatus(404);
});

test('GET /api/orders/{id}/status returns status for known order', function () {
    $user = User::factory()->create();

    $order = Order::create([
        'user_id'                  => $user->id,
        'status'                   => OrderStatus::PendingPayment,
        'guest_name'               => 'Test User',
        'guest_email'              => 'test@test.com',
        'subtotal'                 => '100.00',
        'grand_total'              => '100.00',
        'shipping_address_snapshot' => json_encode([]),
    ]);

    $response = $this->getJson('/api/orders/'.$order->id.'/status');

    $response->assertStatus(200)
        ->assertJsonStructure(['order_id', 'status']);
});
