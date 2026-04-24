<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Marketing\Models\Banner;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AnalyticsService;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

class BannerListService extends BaseService
{
    public function __construct(
        private readonly Banner $banner,
        private readonly AnalyticsService $analyticsService,
    ) {
        $this->setModel($this->banner);
    }

    public function listActiveForPublic(): array
    {
        $now = Carbon::now();

        $rows = $this->banner->newQuery()
            ->where('is_active', true)
            ->where(function ($q) use ($now) {
                $q->whereNull('starts_at')
                    ->orWhere('starts_at', '<=', $now);
            })
            ->where(function ($q) use ($now) {
                $q->whereNull('ends_at')
                    ->orWhere('ends_at', '>=', $now);
            })
            ->orderBy('sort_order')
            ->get();

        $data = $rows->map(fn (Banner $b) => [
            'id' => (string) $b->id,
            'image_url' => $b->image_url,
            'destination_url' => $b->destination_url,
            'sort_order' => $b->sort_order,
        ])->all();

        try {
            $this->analyticsService->track(
                'banners_list_viewed',
                auth('api')->id(),
                ['banner_count' => count($data)],
                'api',
                request()
            );
        } catch (InvalidArgumentException) {
        }

        return [
            'data' => $data,
        ];
    }
}
