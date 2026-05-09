<?php

namespace App\Domains\Commerce\Services;

use App\Domains\Commerce\Enums\OrderStatus;
use App\Domains\Commerce\Models\Order;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AnalyticsService;
use App\Domains\Tracking\Services\AuditLogger;
use App\Domains\Tracking\Services\OrderStatusTracker;
use Illuminate\Support\Facades\DB;

class OrderAdminService extends BaseService
{
    public function __construct(
        private readonly Order $order,
        private readonly OrderStatusTracker $orderStatusTracker,
        private readonly AnalyticsService $analyticsService,
        private readonly AuditLogger $auditLogger,
    ) {
        $this->setModel($this->order);
    }

    public function index(array $options = [], ?\Closure $builderCallback = null)
    {
        $enrich = function ($query) {
            $query->with([
                'user:id,name,email',
                'items',
            ]);
        };
        if ($builderCallback) {
            $enrich = function ($query) use ($enrich, $builderCallback) {
                $enrich($query);
                $builderCallback($query);
            };
        }
        if (empty($options['sort_by'])) {
            $options['sort_by'] = 'created_at';
            $options['sort_order'] = 'desc';
        }

        return parent::index($options, $enrich);
    }

    public function show(string $id)
    {
        $order = $this->findById($id);

        return $order->load(['user', 'items.productVariant.product.coverImage']);
    }

    public function updateByAdmin(string $id, array $data): Order
    {
        return DB::transaction(function () use ($id, $data) {
            $order = $this->findById($id);
            $old = $this->orderAuditSnapshot($order);

            if (array_key_exists('correios_tracking_code', $data)) {
                $order->correios_tracking_code = $data['correios_tracking_code'];
            }

            if (isset($data['status']) && $data['status'] !== $order->status->value) {
                $from = $order->status->value;
                $this->orderStatusTracker->record(
                    $order,
                    $from,
                    $data['status'],
                    'admin',
                    ['actor_user_id' => (string) auth('api')->id()]
                );
                $order->refresh();
                if (OrderStatus::from($data['status']) === OrderStatus::Shipped) {
                    if ($order->shipped_at === null) {
                        $order->forceFill(['shipped_at' => now()])->save();
                    }
                    $this->analyticsService->track(
                        'order_shipped',
                        $order->user_id,
                        [
                            'order_id' => (string) $order->id,
                            'source' => 'admin',
                        ],
                        'api',
                        request()
                    );
                }
            } else {
                $order->save();
            }

            $order->refresh();
            $this->auditLogger->log(
                (string) auth('api')->id(),
                'orders.update',
                $order,
                $old,
                $this->orderAuditSnapshot($order),
                request()
            );

            return $order->load(['user', 'items.productVariant.product.coverImage']);
        });
    }

    private function orderAuditSnapshot(Order $order): array
    {
        $a = $order->getAttributes();
        if (isset($a['status']) && $a['status'] instanceof \UnitEnum) {
            $a['status'] = $a['status'] instanceof \BackedEnum ? $a['status']->value : (string) $a['status'];
        }

        return $a;
    }
}
