<?php

test('POST /api/shipping-quote returns 422 without required fields', function () {
    $response = $this->postJson('/api/shipping-quote', []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['postal_code', 'items']);
});

test('POST /api/shipping-quote returns 422 for invalid variant', function () {
    $response = $this->postJson('/api/shipping-quote', [
        'postal_code' => '01310100',
        'items' => [['product_variant_id' => 'nonexistent-variant-id', 'quantity' => 1]],
    ]);

    // InvalidArgumentException from bad variant → returns 422 (not 500)
    $response->assertStatus(422);
});
