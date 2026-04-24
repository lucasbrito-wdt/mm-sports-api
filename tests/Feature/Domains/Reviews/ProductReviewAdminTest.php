<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Reviews\Enums\ReviewModerationStatus;
use App\Domains\Reviews\Models\ProductReview;
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

it('rejeita listagem de reviews admin sem permissão de admin', function () {
    $this->withHeaders(jwtHeaders($this->user))
        ->getJson('/api/admin/product-reviews')
        ->assertForbidden();
});

it('admin lista reviews', function () {
    [$p] = CommerceFixtures::publishedProductWithVariant();
    $this->withHeaders(jwtHeaders($this->user))
        ->postJson('/api/reviews', [
            'product_id' => (string) $p->id,
            'rating' => 5,
            'body' => 'Muito bom',
        ])
        ->assertCreated();

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/product-reviews?per_page=20&filters[moderation_status]=pending');
    $res->assertOk();
    expect($res->json('data'))->not->toBeEmpty();
});

it('admin aprova review e gera audit', function () {
    [$p] = CommerceFixtures::publishedProductWithVariant();
    $this->withHeaders(jwtHeaders($this->user))
        ->postJson('/api/reviews', [
            'product_id' => (string) $p->id,
            'rating' => 4,
            'body' => 'Ok',
        ]);
    $review = ProductReview::query()->where('user_id', $this->user->id)->firstOrFail();
    $id = (string) $review->id;

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->patchJson("/api/admin/product-reviews/{$id}", [
            'moderation_status' => 'approved',
        ]);
    $res->assertOk();
    $review->refresh();
    expect($review->moderation_status)->toBe(ReviewModerationStatus::Approved);
    expect(
        AuditLog::query()
            ->where('auditable_id', $id)
            ->where('action', 'product_reviews.moderate')
            ->exists()
    )->toBeTrue();
});
