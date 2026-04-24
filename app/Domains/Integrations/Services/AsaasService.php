<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Commerce\Models\Order;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AsaasService
{
    public function __construct() {}

    /**
     * Cria cobrança no Asaas. Sem ASAAS_API_KEY, gera id fake (sem HTTP).
     *
     * @return array{asaas_payment_id: string, asaas_customer_id: string|null, raw: mixed}
     */
    public function createPayment(Order $order, float $value): array
    {
        $key = config('services.asaas.api_key');
        if (empty($key)) {
            $paymentId = 'test_'.(string) Str::ulid();

            return [
                'asaas_payment_id' => $paymentId,
                'asaas_customer_id' => null,
                'raw' => ['fake' => true],
            ];
        }

        $base = rtrim((string) config('services.asaas.base_url', 'https://api.asaas.com/v3'), '/');
        $payload = [
            'customer' => $order->asaas_customer_id,
            'billingType' => 'UNDEFINED',
            'value' => round($value, 2),
            'description' => 'Order '.(string) $order->id,
            'externalReference' => (string) $order->id,
        ];
        $response = Http::withHeaders(['access_token' => $key])
            ->post($base.'/payments', array_filter($payload, fn ($v) => $v !== null));
        if (! $response->successful()) {
            $response->throw();
        }
        $json = $response->json();

        return [
            'asaas_payment_id' => (string) ($json['id'] ?? ''),
            'asaas_customer_id' => $json['customer'] ?? null,
            'raw' => $json,
        ];
    }
}
