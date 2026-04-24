<?php

use App\Domains\Tracking\Services\AnalyticsService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('rejeita evento cujo nome não está na allowlist', function () {
    $service = app(AnalyticsService::class);
    $service->track('evento_inexistente_xyz', null, [], 'api', request());
})->throws(\InvalidArgumentException::class, 'Unknown analytics event: evento_inexistente_xyz');
