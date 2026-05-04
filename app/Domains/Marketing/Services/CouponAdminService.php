<?php

namespace App\Domains\Marketing\Services;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use App\Domains\Marketing\Models\Coupon;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AuditLogger;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class CouponAdminService extends BaseService
{
    public function __construct(
        private readonly Coupon $coupon,
        private readonly AuditLogger $auditLogger,
    ) {
        $this->setModel($this->coupon);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null)
    {
        if (empty($options['sort_by'])) {
            $options['sort_by'] = 'created_at';
            $options['sort_order'] = 'desc';
        }

        return parent::index($options, $builderCallback);
    }

    public function store(array $data)
    {
        $data['code'] = strtoupper(trim((string) ($data['code'] ?? '')));
        if (! array_key_exists('active', $data)) {
            $data['active'] = true;
        }

        return DB::transaction(function () use ($data) {
            $model = $this->coupon->newQuery()->create($this->onlyFillable($data));
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'coupons.create',
                $model,
                null,
                $this->auditPayload($model),
                request(),
            );

            return $model;
        });
    }

    public function update(array $data, string $id)
    {
        return DB::transaction(function () use ($data, $id) {
            /** @var Coupon $record */
            $record = $this->findById($id);
            $old = $this->auditPayload($record);
            if (isset($data['code'])) {
                $data['code'] = strtoupper(trim((string) $data['code']));
            }
            $payload = $this->onlyFillable($data);
            if ($payload !== []) {
                $record->update($payload);
            }
            $record->refresh();
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'coupons.update',
                $record,
                $old,
                $this->auditPayload($record),
                request(),
            );

            return $record;
        });
    }

    public function destroy($id)
    {
        return DB::transaction(function () use ($id) {
            $record = $this->findById($id);
            $old = $this->auditPayload($record);
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'coupons.delete',
                $record,
                $old,
                null,
                request(),
            );

            return $record->delete();
        });
    }

    /**
     * @return array{
     *   coupon_id: string,
     *   code: string,
     *   usage_count: int,
     *   usage_limit: int|null,
     *   total_discount: string,
     *   total_revenue: string,
     *   orders_completed: int,
     *   usage_by_day: list<array{date: string, count: int, discount: string}>
     * }
     */
    public function metrics(string $id, int $days = 30): array
    {
        /** @var Coupon $coupon */
        $coupon = $this->findById($id);

        $completedStatuses = [
            OrderStatus::Paid->value,
            OrderStatus::Shipped->value,
        ];

        $base = Order::query()->where('coupon_id', $coupon->id);

        $totals = (clone $base)
            ->whereIn('status', $completedStatuses)
            ->selectRaw('COUNT(*) as orders_completed, COALESCE(SUM(discount_total), 0) as total_discount, COALESCE(SUM(grand_total), 0) as total_revenue')
            ->first();

        $since = Carbon::now()->subDays($days)->startOfDay();
        $byDay = (clone $base)
            ->where('created_at', '>=', $since)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as count, COALESCE(SUM(discount_total), 0) as discount')
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'coupon_id' => (string) $coupon->id,
            'code' => $coupon->code,
            'usage_count' => $coupon->usage_count,
            'usage_limit' => $coupon->usage_limit,
            'total_discount' => (string) ($totals->total_discount ?? '0'),
            'total_revenue' => (string) ($totals->total_revenue ?? '0'),
            'orders_completed' => (int) ($totals->orders_completed ?? 0),
            'usage_by_day' => $byDay->map(fn ($row) => [
                'date' => (string) $row->date,
                'count' => (int) $row->count,
                'discount' => (string) $row->discount,
            ])->all(),
        ];
    }

    private function onlyFillable(array $data): array
    {
        $keys = $this->getModel()->getFillable();

        return array_intersect_key($data, array_flip($keys));
    }

    private function auditPayload(Coupon $coupon): array
    {
        $a = $coupon->getAttributes();
        if (isset($a['type']) && $a['type'] instanceof \UnitEnum) {
            $a['type'] = $a['type'] instanceof \BackedEnum ? $a['type']->value : (string) $a['type'];
        }

        return $a;
    }
}
