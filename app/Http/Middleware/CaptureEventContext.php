<?php

namespace App\Http\Middleware;

use App\Domains\Tracking\Services\EventContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class CaptureEventContext
{
    private const ATTRIBUTION_TTL = 60 * 24 * 30;

    public function handle(Request $request, Closure $next)
    {
        $ctx = app(EventContext::class);

        $ctx->sessionId   = $request->header('X-Session-Id')
            ?? $request->cookie('_sess_id')
            ?? (string) Str::uuid();

        $ctx->anonymousId = $request->header('X-Anonymous-Id')
            ?? $request->cookie('_anon_id')
            ?? (string) Str::uuid();

        $ctx->userId = $request->user()?->id ? (string) $request->user()->id : null;

        $utmParams = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content'];
        $hasUtm = collect($utmParams)->some(fn($p) => $request->filled($p));

        if ($hasUtm) {
            $ctx->utmSource   = substr((string) $request->query('utm_source',   ''), 0, 100) ?: null;
            $ctx->utmMedium   = substr((string) $request->query('utm_medium',   ''), 0, 100) ?: null;
            $ctx->utmCampaign = substr((string) $request->query('utm_campaign', ''), 0, 100) ?: null;
            $ctx->utmTerm     = substr((string) $request->query('utm_term',     ''), 0, 100) ?: null;
            $ctx->utmContent  = substr((string) $request->query('utm_content',  ''), 0, 100) ?: null;
            $ctx->landingPage = $request->fullUrl()
                ? substr($request->fullUrl(), 0, 500)
                : null;
            $ctx->referrer = $request->header('referer')
                ? substr($request->header('referer'), 0, 500)
                : null;
        } else {
            $ctx->utmSource   = $request->cookie('_utm_source');
            $ctx->utmMedium   = $request->cookie('_utm_medium');
            $ctx->utmCampaign = $request->cookie('_utm_campaign');
            $ctx->utmTerm     = $request->cookie('_utm_term');
            $ctx->utmContent  = $request->cookie('_utm_content');
            $ctx->landingPage = $request->cookie('_landing_page');
            $ctx->referrer    = $request->cookie('_referrer');
        }

        $ctx->ipAddress  = $request->ip();
        $ctx->userAgent  = $request->userAgent()
            ? substr($request->userAgent(), 0, 500)
            : null;
        $ctx->deviceType = $this->detectDevice($request->userAgent());

        $ctx->country = $request->header('CF-IPCountry');
        $ctx->city    = $request->header('CF-IPCity');

        $response = $next($request);

        if ($hasUtm) {
            $secure = $request->secure();
            foreach ([
                '_utm_source'   => $ctx->utmSource,
                '_utm_medium'   => $ctx->utmMedium,
                '_utm_campaign' => $ctx->utmCampaign,
                '_utm_term'     => $ctx->utmTerm,
                '_utm_content'  => $ctx->utmContent,
                '_landing_page' => $ctx->landingPage,
                '_referrer'     => $ctx->referrer,
            ] as $cookieName => $value) {
                if ($value !== null) {
                    $response->headers->setCookie(
                        \Symfony\Component\HttpFoundation\Cookie::create(
                            $cookieName,
                            $value,
                            now()->addMinutes(self::ATTRIBUTION_TTL)->getTimestamp(),
                            '/',
                            null,
                            $secure,
                            true,
                            false,
                            'lax'
                        )
                    );
                }
            }
        }

        return $response;
    }

    private function detectDevice(?string $userAgent): string
    {
        if (!$userAgent) return 'unknown';
        $ua = strtolower($userAgent);
        if (preg_match('/ipad|tablet|android(?!.*mobile)/', $ua)) return 'tablet';
        if (preg_match('/mobile|android|iphone|ipod/', $ua)) return 'mobile';
        return 'desktop';
    }
}
