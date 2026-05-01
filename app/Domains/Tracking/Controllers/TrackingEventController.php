<?php

namespace App\Domains\Tracking\Controllers;

use App\Domains\Tracking\Models\TrackingEvent;
use App\Domains\Tracking\Services\EventContext;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class TrackingEventController extends Controller
{
    public function batch(Request $request): JsonResponse
    {
        $request->validate([
            'events'                   => 'required|array|max:100',
            'events.*.event_name'      => 'required|string|max:64',
            'events.*.event_timestamp' => 'nullable|date',
        ]);

        $ctx = app(EventContext::class);
        $now = now();

        $rows = collect($request->input('events'))->map(function ($e) use ($ctx, $now) {
            return [
                'event_name'      => $e['event_name'],
                'event_timestamp' => $e['event_timestamp'] ?? $now,
                'session_id'      => $e['session_id']    ?? $ctx->sessionId   ?? (string) \Illuminate\Support\Str::uuid(),
                'anonymous_id'    => $e['anonymous_id']  ?? $ctx->anonymousId ?? (string) \Illuminate\Support\Str::uuid(),
                'user_id'         => $e['user_id']       ?? $ctx->userId,
                'properties'      => $e['properties'] ?? [],
                'device_type'     => $e['device_type']   ?? $ctx->deviceType,
                'ip_address'      => $ctx->ipAddress,
                'user_agent'      => $ctx->userAgent,
                'country'         => $ctx->country,
                'city'            => $ctx->city,
                'utm_source'      => $ctx->utmSource,
                'utm_medium'      => $ctx->utmMedium,
                'utm_campaign'    => $ctx->utmCampaign,
                'utm_term'        => $ctx->utmTerm,
                'utm_content'     => $ctx->utmContent,
                'referrer'        => $e['referrer']      ?? $ctx->referrer,
                'landing_page'    => $e['landing_page']  ?? $ctx->landingPage,
                'product_id'      => $e['product_id']    ?? null,
                'order_id'        => $e['order_id']      ?? null,
                'revenue_cents'   => $e['revenue_cents'] ?? null,
                'currency'        => $e['currency']      ?? 'BRL',
            ];
        });

        foreach ($rows as $row) {
            TrackingEvent::create($row);
        }

        return response()->json(['accepted' => $rows->count()], 202);
    }
}
