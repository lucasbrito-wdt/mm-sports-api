<?php

use App\Domains\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

it('adiciona variante na wishlist, lista e remove', function () {
    $user = User::factory()->create();
    [, $v] = CommerceFixtures::publishedProductWithVariant();

    $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/wishlist', ['product_variant_id' => (string) $v->id])
        ->assertCreated()
        ->assertJsonStructure(['data' => ['id']]);

    $this->withHeaders(jwtHeaders($user))
        ->getJson('/api/wishlist')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.sku', $v->sku);

    $this->withHeaders(jwtHeaders($user))
        ->deleteJson("/api/wishlist/{$v->id}")
        ->assertOk()
        ->assertJson(['ok' => true]);

    $this->withHeaders(jwtHeaders($user))
        ->getJson('/api/wishlist')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});
