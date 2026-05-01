<?php

namespace App\Domains\Tracking\Services;

class EventContext
{
    public ?string $sessionId = null;
    public ?string $anonymousId = null;
    public ?string $userId = null;

    public ?string $utmSource = null;
    public ?string $utmMedium = null;
    public ?string $utmCampaign = null;
    public ?string $utmTerm = null;
    public ?string $utmContent = null;

    public ?string $referrer = null;
    public ?string $landingPage = null;

    public ?string $ipAddress = null;
    public ?string $userAgent = null;
    public ?string $deviceType = null;
    public ?string $country = null;
    public ?string $city = null;

    public function toArray(): array
    {
        return [
            'session_id'    => $this->sessionId,
            'anonymous_id'  => $this->anonymousId,
            'user_id'       => $this->userId,
            'utm_source'    => $this->utmSource,
            'utm_medium'    => $this->utmMedium,
            'utm_campaign'  => $this->utmCampaign,
            'utm_term'      => $this->utmTerm,
            'utm_content'   => $this->utmContent,
            'referrer'      => $this->referrer,
            'landing_page'  => $this->landingPage,
            'ip_address'    => $this->ipAddress,
            'user_agent'    => $this->userAgent,
            'device_type'   => $this->deviceType,
            'country'       => $this->country,
            'city'          => $this->city,
        ];
    }
}
