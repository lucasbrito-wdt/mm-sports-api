<?php

namespace App\Domains\Integrations\Services;

use App\Domains\Commerce\Models\Order;
use Carbon\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
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
        if (! empty($this->apiKey())) {
            return true;
        }

        if (app()->environment('production')) {
            throw new \RuntimeException('ASAAS_API_KEY ausente em produção; checkout não pode operar em modo mock.');
        }

        Log::warning('[Asaas] ASAAS_API_KEY não configurada — usando respostas mock (apenas para dev).');

        return false;
    }

    /**
     * Resolve (ou cria) o customer no Asaas, reaproveitando ID persistido no usuário e por CPF remoto.
     */
    public function ensureCustomer(Order $order): string
    {
        if (! empty($order->asaas_customer_id)) {
            return (string) $order->asaas_customer_id;
        }

        $owner = $order->user;
        $cpf = preg_replace('/\D/', '', (string) ($owner?->cpf ?? ''));
        $phone = preg_replace('/\D/', '', (string) ($owner?->phone ?? ''));
        $name = $owner?->name ?? 'Customer';
        $email = $owner?->email;

        if ($owner && ! empty($owner->asaas_customer_id)) {
            return (string) $owner->asaas_customer_id;
        }

        if (! $this->hasCreds()) {
            return 'test_cust_'.(string) Str::ulid();
        }

        $remoteId = $cpf !== '' ? $this->findRemoteCustomerByCpf($cpf) : null;

        if ($remoteId === null) {
            $payload = array_filter([
                'name' => $name,
                'email' => $email,
                'cpfCnpj' => $cpf ?: null,
                'phone' => $phone !== '' ? '55'.$phone : null,
                'externalReference' => $owner?->id ?? (string) $order->id,
            ], fn ($v) => $v !== null);

            $response = Http::withHeaders(['access_token' => $this->apiKey()])
                ->post($this->baseUrl().'/customers', $payload);

            if (! $response->successful()) {
                $response->throw();
            }

            $remoteId = (string) ($response->json('id') ?? '');
        }

        if ($owner) {
            $owner->forceFill(['asaas_customer_id' => $remoteId])->save();
        }

        return $remoteId;
    }

    private function findRemoteCustomerByCpf(string $cpf): ?string
    {
        try {
            $response = Http::withHeaders(['access_token' => $this->apiKey()])
                ->get($this->baseUrl().'/customers', ['cpfCnpj' => $cpf, 'limit' => 1]);

            if (! $response->successful()) {
                return null;
            }

            $first = $response->json('data.0.id');

            return $first ? (string) $first : null;
        } catch (\Throwable $e) {
            Log::warning('[Asaas] Falha em lookup de customer por CPF', ['cpf' => $cpf, 'error' => $e->getMessage()]);

            return null;
        }
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
     * @param  array{
     *   token?: string|null,
     *   holder_name?: string, number?: string, expiry_month?: string, expiry_year?: string, ccv?: string,
     *   holder_cpf?: string, holder_phone?: string, holder_postal_code?: string, holder_address_number?: string,
     *   holder_address?: string|null, holder_district?: string|null, holder_complement?: string|null
     * }  $cardData
     * @return array{asaas_payment_id: string, asaas_customer_id: string|null, status: string, credit_card_token: string|null, credit_card_brand: string|null, credit_card_last4: string|null}
     */
    public function createCardPayment(Order $order, array $cardData, string $remoteIp, int $installments = 1): array
    {
        if (! $this->hasCreds()) {
            return [
                'asaas_payment_id' => 'test_'.(string) Str::ulid(),
                'asaas_customer_id' => null,
                'status' => 'CONFIRMED',
                'credit_card_token' => 'test_card_'.(string) Str::ulid(),
                'credit_card_brand' => 'VISA',
                'credit_card_last4' => '4242',
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
        ];

        if (! empty($cardData['token'])) {
            $payload['creditCardToken'] = $cardData['token'];
        } else {
            $payload['creditCard'] = [
                'holderName' => $cardData['holder_name'],
                'number' => preg_replace('/\D/', '', $cardData['number']),
                'expiryMonth' => $cardData['expiry_month'],
                'expiryYear' => $cardData['expiry_year'],
                'ccv' => $cardData['ccv'],
            ];
            $payload['creditCardHolderInfo'] = array_filter([
                'name' => $cardData['holder_name'],
                'email' => $order->user?->email ?? '',
                'cpfCnpj' => preg_replace('/\D/', '', $cardData['holder_cpf']),
                'postalCode' => preg_replace('/\D/', '', $cardData['holder_postal_code']),
                'addressNumber' => $cardData['holder_address_number'],
                'address' => $cardData['holder_address'] ?? null,
                'province' => $cardData['holder_district'] ?? null,
                'addressComplement' => $cardData['holder_complement'] ?? null,
                'phone' => preg_replace('/\D/', '', $cardData['holder_phone']),
            ], fn ($v) => $v !== null && $v !== '');
        }

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
        $cc = $json['creditCard'] ?? [];

        return [
            'asaas_payment_id' => (string) ($json['id'] ?? ''),
            'asaas_customer_id' => $customerId,
            'status' => (string) ($json['status'] ?? 'PENDING'),
            'credit_card_token' => $json['creditCardToken'] ?? ($cc['creditCardToken'] ?? null),
            'credit_card_brand' => $cc['creditCardBrand'] ?? null,
            'credit_card_last4' => $cc['creditCardNumber'] ?? null,
        ];
    }

    /**
     * Mantido para compatibilidade com OrderService::createFromUser() (fluxo legado, billing_type indefinido).
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

    /**
     * Resolve dados do portador (cardholder info) consolidando o snapshot do pedido com o input do cartão.
     * Quando `use_shipping_as_billing` (ou holder_* ausentes), preenche a partir do shipping_address_snapshot.
     */
    public static function buildCardData(array $rawCardInput, Order $order, bool $useShippingAsBilling = true): array
    {
        $snap = is_array($order->shipping_address_snapshot) ? $order->shipping_address_snapshot : [];

        $holderPostal = $rawCardInput['holder_postal_code'] ?? null;
        $holderNumber = $rawCardInput['holder_address_number'] ?? null;
        $holderAddress = $rawCardInput['holder_address'] ?? null;
        $holderDistrict = $rawCardInput['holder_district'] ?? null;
        $holderComplement = $rawCardInput['holder_complement'] ?? null;

        if ($useShippingAsBilling) {
            $holderPostal ??= $snap['postal_code'] ?? null;
            $holderNumber ??= $snap['number'] ?? null;
            $holderAddress ??= $snap['street'] ?? null;
            $holderDistrict ??= $snap['district'] ?? null;
            $holderComplement ??= $snap['complement'] ?? null;
        }

        return array_merge($rawCardInput, [
            'holder_postal_code' => $holderPostal,
            'holder_address_number' => $holderNumber,
            'holder_address' => $holderAddress,
            'holder_district' => $holderDistrict,
            'holder_complement' => $holderComplement,
        ]);
    }
}
