<?php

use App\Domains\Auth\Models\User;
use App\Domains\Reviews\Enums\ReviewModerationStatus;
use App\Domains\Reviews\Models\ProductReview;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\CommerceFixtures;

uses(RefreshDatabase::class);

it('cria review pendente com rating e produto', function () {
    $user = User::factory()->create();
    [$p] = CommerceFixtures::publishedProductWithVariant();
    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/reviews', [
            'product_id' => (string) $p->id,
            'rating' => 5,
            'body' => 'Ótima qualidade.',
        ]);
    $res->assertCreated();
    $res->assertJsonPath('data.moderation_status', ReviewModerationStatus::Pending->value);
    expect(ProductReview::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('valida rating mínimo no review', function () {
    $user = User::factory()->create();
    [$p] = CommerceFixtures::publishedProductWithVariant();
    $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/reviews', [
            'product_id' => (string) $p->id,
            'rating' => 0,
            'body' => 'x',
        ])
        ->assertUnprocessable();
});
