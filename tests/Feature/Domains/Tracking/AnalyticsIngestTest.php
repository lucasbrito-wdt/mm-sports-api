<?php

use App\Domains\Tracking\Models\AnalyticsEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('aceita lote de eventos permitidos e persiste', function () {
    $payload = [
        'events' => [
            [
                'name' => 'product_viewed',
                'properties' => ['product_id' => '01HZTEST1234'],
            ],
            [
                'name' => 'search_executed',
                'properties' => ['q' => 'camisa'],
            ],
        ],
    ];

    $res = $this->postJson('/api/analytics/events', $payload);
    $res->assertOk();
    $res->assertJson(['stored' => 2]);
    expect(AnalyticsEvent::query()->count())->toBe(2);
});

it('ingest rejeita lote cujo nome não está na allowlist (422, nada persistido)', function () {
    $payload = [
        'events' => [
            ['name' => 'invalid_event_name_for_ingest', 'properties' => []],
        ],
    ];

    $res = $this->postJson('/api/analytics/events', $payload);
    $res->assertStatus(422);
    expect(AnalyticsEvent::query()->count())->toBe(0);
});
