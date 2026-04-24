<?php

use App\Domains\Marketing\Models\Banner;
use App\Domains\Tracking\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('retorna lista vazia sem banners ativos', function () {
    $res = $this->getJson('/api/banners');
    $res->assertOk();
    $res->assertJson(['data' => []]);
});

it('lista banner ativo na janela de datas', function () {
    Banner::query()->create([
        'id' => (string) Str::ulid(),
        'internal_title' => 'Hero',
        'image_url' => 'https://example.com/b.png',
        'destination_url' => 'https://example.com/promo',
        'sort_order' => 0,
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => null,
        'device' => null,
    ]);
    $res = $this->getJson('/api/banners');
    $res->assertOk();
    $res->assertJsonCount(1, 'data');
    $res->assertJsonPath('data.0.destination_url', 'https://example.com/promo');
    expect(AnalyticsEvent::query()->where('name', 'banners_list_viewed')->exists())->toBeTrue();
    $ev = AnalyticsEvent::query()->where('name', 'banners_list_viewed')->first();
    expect($ev->properties['banner_count'] ?? null)->toBe(1);
});
