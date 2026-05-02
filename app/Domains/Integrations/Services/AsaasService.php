<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Commerce\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class AsaasService
{
    private function apiKey(): string
    {
        return (string) config('asaas.api_key', '');
    }

    private function baseUrl(): string
    {
        $configured = config('asaas.base_url');
        if ($configured) {
            return rtrim((string) $configured, '/');
        }
        $env = (string) config('asaas.environment', 'sandbox');

        return $env === 'production'
            ? 'https://api.asaas.com/v3'
            : 'https://sandbox.asaas.com/api/v3';
    }

    private function hasCreds(): bool
    {
        return ! empty($this->apiKey());
    }

    public function ensureCustomer(Order $order): string
    {
        if (! empty($order->asaas_customer_id)) {
            return (string) $order->asaas_customer_id;
        }

        if (! $this->hasCreds()) {
            return 'test_cust_'.(string) Str::ulid();
        }

        $cpf = preg_replace('/\D/', '', (string) ($order->guest_cpf ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($order->guest_phone ?? ''));

        $payload = array_filter([
            'name' => $order->guest_name ?? ($order->user?->name ?? 'Customer'),
            'email' => $order->guest_email ?? ($order->user?->email ?? null),
            'cpfCnpj' => $cpf ?: null,
            'phone' => $phone ? '55'.$phone : null,
            'externalReference' => (string) $order->id,
        ], fn ($v) => $v !== null);

        $response = Http::withHeaders(['access_token' => $this->apiKey()])
            ->post($this->baseUrl().'/customers', $payload);

        if (! $response->successful()) {
            $response->throw();
        }

        return (string) ($response->json('id') ?? '');
    }

    /**
     * @return array{asaas_payment_id: string, asaas_customer_id: string|null, qr_code: string, copy_paste: string, expires_at: string}
     */
    public function createPixPayment(Order $order): array
    {
        if (! $this->hasCreds()) {
            return [
                'asaas_payment_id' => 'test_'.(string) Str::ulid(),
                'asaas_customer_id' => null,
                'qr_code' => base64_encode('fake-qr-image-data'),
                'copy_paste' => '00020126580014BR.GOV.BCB.PIX0136test_key_'.(string) Str::ulid(),
                'expires_at' => Carbon::now()->addMinutes(30)->toIso8601String(),
            ];
        }

        $customerId = $this->ensureCustomer($order);
        $dueDate = Carbon::now()->addMinutes(30)->toDateString();

        $payment = Http::withHeaders(['access_token' => $this->apiKey()])
            ->post($this->baseUrl().'/payments', [
                'customer' => $customerId,
                'billingType' => 'PIX',
                'value' => round((float) $order->grand_total, 2),
                'dueDate' => $dueDate,
                'description' => 'Pedido #'.(string) $order->id,
                'externalReference' => (string) $order->id,
            ]);

        if (! $payment->successful()) {
            $payment->throw();
        }

        $paymentId = $payment->json('id');

        $qr = Http::withHeaders(['access_token' => $this->apiKey()])
            ->get($this->baseUrl().'/payments/'.$paymentId.'/pixQrCode');

        if (! $qr->successful()) {
            $qr->throw();
        }

        return [
            'asaas_payment_id' => $paymentId,
            'asaas_customer_id' => $customerId,
            'qr_code' => (string) ($qr->json('encodedImage') ?? ''),
            'copy_paste' => (string) ($qr->json('payload') ?? ''),
            'expires_at' => (string) ($qr->json('expirationDate') ?? Carbon::now()->addMinutes(30)->toIso8601String()),
        ];
    }

    /**
     * @return array{asaas_payment_id: string, asaas_customer_id: string|null, boleto_url: string, barcode: string, due_date: string}
     */
    public function createBoletoPayment(Order $order): array
    {
        if (! $this->hasCreds()) {
            return [
                'asaas_payment_id' => 'test_'.(string) Str::ulid(),
                'asaas_customer_id' => null,
                'boleto_url' => 'https://sandbox.asaas.com/b/boleto_test',
                'barcode' => '34191.75001 00000.000007 00000.000000 1 00000000000000',
                'due_date' => Carbon::now()->addDays(3)->toDateString(),
            ];
        }

        $customerId = $this->ensureCustomer($order);
        $dueDate = Carbon::now()->addDays(3)->toDateString();

        $response = Http::withHeaders(['access_token' => $this->apiKey()])
            ->post($this->baseUrl().'/payments', [
                'customer' => $customerId,
                'billingType' => 'BOLETO',
                'value' => round((float) $order->grand_total, 2),
                'dueDate' => $dueDate,
                'description' => 'Pedido #'.(string) $order->id,
                'externalReference' => (string) $order->id,
            ]);

        if (! $response->successful()) {
            $response->throw();
        }

        $json = $response->json();

        return [
            'asaas_payment_id' => (string) ($json['id'] ?? ''),
            'asaas_customer_id' => $customerId,
            'boleto_url' => (string) ($json['bankSlipUrl'] ?? ''),
            'barcode' => (string) ($json['nossoNumero'] ?? ''),
            'due_date' => (string) ($json['dueDate'] ?? $dueDate),
        ];
    }

    /**
     * @param  array{holder_name: string, number: string, expiry_month: string, expiry_year: string,
     *               ccv: string, holder_cpf: string, holder_phone: string,
     *               holder_postal_code: string, holder_address_number: string}  $cardData
     * @return array{asaas_payment_id: string, asaas_customer_id: string|null, status: string}
     */
    public function createCardPayment(Order $order, array $cardData, string $remoteIp, int $installments = 1): array
    {
        if (! $this->hasCreds()) {
            return [
                'asaas_payment_id' => 'test_'.(string) Str::ulid(),
                'asaas_customer_id' => null,
                'status' => 'CONFIRMED',
            ];
        }

        $customerId = $this->ensureCustomer($order);
        $grandTotal = round((float) $order->grand_total, 2);
        $dueDate = Carbon::now()->toDateString();

        $payload = [
            'customer' => $customerId,
            'billingType' => 'CREDIT_CARD',
            'value' => $grandTotal,
            'dueDate' => $dueDate,
            'description' => 'Pedido #'.(string) $order->id,
            'externalReference' => (string) $order->id,
            'remoteIp' => $remoteIp,
            'creditCard' => [
                'holderName' => $cardData['holder_name'],
                'number' => preg_replace('/\D/', '', $cardData['number']),
                'expiryMonth' => $cardData['expiry_month'],
                'expiryYear' => $cardData['expiry_year'],
                'ccv' => $cardData['ccv'],
            ],
            'creditCardHolderInfo' => [
                'name' => $cardData['holder_name'],
                'email' => $order->guest_email ?? ($order->user?->email ?? ''),
                'cpfCnpj' => preg_replace('/\D/', '', $cardData['holder_cpf']),
                'postalCode' => preg_replace('/\D/', '', $cardData['holder_postal_code']),
                'addressNumber' => $cardData['holder_address_number'],
                'phone' => preg_replace('/\D/', '', $cardData['holder_phone']),
            ],
        ];

        if ($installments > 1) {
            $payload['installmentCount'] = $installments;
            $payload['installmentValue'] = round($grandTotal / $installments, 2);
        }

        $response = Http::withHeaders(['access_token' => $this->apiKey()])
            ->post($this->baseUrl().'/payments', $payload);

        if (! $response->successful()) {
            $response->throw();
        }

        $json = $response->json();

        return [
            'asaas_payment_id' => (string) ($json['id'] ?? ''),
            'asaas_customer_id' => $customerId,
            'status' => (string) ($json['status'] ?? 'PENDING'),
            'credit_card_token' => $json['creditCardToken'] ?? null,
        ];
    }

    /**
     * Kept for backward compatibility with OrderService::createFromUser().
     *
     * @return array{asaas_payment_id: string, asaas_customer_id: string|null, raw: mixed}
     */
    public function createPayment(Order $order, float $value): array
    {
        if (! $this->hasCreds()) {
            return [
                'asaas_payment_id' => 'test_'.(string) Str::ulid(),
                'asaas_customer_id' => null,
                'raw' => ['fake' => true],
            ];
        }

        $customerId = $this->ensureCustomer($order);
        $response = Http::withHeaders(['access_token' => $this->apiKey()])
            ->post($this->baseUrl().'/payments', [
                'customer' => $customerId,
                'billingType' => 'UNDEFINED',
                'value' => round($value, 2),
                'dueDate' => now()->addDays(3)->toDateString(),
                'description' => 'Pedido #'.(string) $order->id,
                'externalReference' => (string) $order->id,
            ]);

        if (! $response->successful()) {
            $response->throw();
        }

        $json = $response->json();

        return [
            'asaas_payment_id' => (string) ($json['id'] ?? ''),
            'asaas_customer_id' => $customerId,
            'raw' => $json,
        ];
    }
}
