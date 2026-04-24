<?php

namespace App\Domains\Tracking\Controllers;

use App\Domains\Shared\Controller\BaseController;
use App\Domains\Tracking\Requests\StoreAnalyticsEventsRequest;
use App\Domains\Tracking\Services\AnalyticsService;
use Illuminate\Http\JsonResponse;
use InvalidArgumentException;

class AnalyticsEventController extends BaseController
{
    public function __construct(
        private readonly AnalyticsService $analyticsService,
    ) {
        parent::__construct();
    }

    public function ingest(StoreAnalyticsEventsRequest $request): JsonResponse
    {
        $userId = auth('api')->id();
        $events = $request->validated()['events'] ?? [];
        $stored = 0;
        foreach ($events as $row) {
            try {
                $this->analyticsService->track(
                    $row['name'],
                    $userId,
                    $row['properties'] ?? [],
                    'api',
                    $request
                );
                $stored++;
            } catch (InvalidArgumentException) {
                // skip invalid names if any slip through
            }
        }

        return response()->json(['stored' => $stored]);
    }
}
