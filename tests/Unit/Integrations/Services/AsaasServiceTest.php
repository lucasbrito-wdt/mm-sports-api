<?php

use App\Domains\Commerce\Models\Order;
use App\Domains\Integrations\Services\AsaasService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->order = Mockery::mock(Order::class);
    $this->order->shouldReceive('getAttribute')->with('id')->andReturn('01HZ000000000000000000001');
    $this->order->shouldReceive('getAttribute')->with('grand_total')->andReturn(150.00);
    $this->order->shouldReceive('getAttribute')->with('asaas_customer_id')->andReturn(null);
    $this->order->shouldReceive('getAttribute')->with('user')->andReturn(null);
    $this->service = new AsaasService;
});

test('createPixPayment returns qr_code, copy_paste and expires_at keys', function () {
    config(['asaas.api_key' => '']);

    $result = $this->service->createPixPayment($this->order);

    expect($result)->toHaveKeys(['qr_code', 'copy_paste', 'expires_at']);
});

test('createBoletoPayment returns boleto_url, barcode and due_date keys', function () {
    config(['asaas.api_key' => '']);

    $result = $this->service->createBoletoPayment($this->order);

    expect($result)->toHaveKeys(['boleto_url', 'barcode', 'due_date']);
});

test('createCardPayment returns status key', function () {
    config(['asaas.api_key' => '']);

    $cardData = [
        'holder_name' => 'JOAO SILVA',
        'number' => '4111111111111111',
        'expiry_month' => '12',
        'expiry_year' => '2028',
        'ccv' => '123',
        'holder_cpf' => '12345678901',
        'holder_phone' => '11987654321',
        'holder_postal_code' => '01310100',
        'holder_address_number' => '100',
    ];

    $result = $this->service->createCardPayment($this->order, $cardData, '127.0.0.1', 1);

    expect($result)->toHaveKey('status')
        ->and($result)->toHaveKey('credit_card_token')
        ->and($result['credit_card_token'])->toBeString();
});

test('buildCardData fills holder_* from shipping snapshot when use_shipping_as_billing is true', function () {
    $order = new Order;
    $order->shipping_address_snapshot = [
        'postal_code' => '58200-230',
        'street' => 'Rua José Bonifácio',
        'number' => '701',
        'district' => 'Juá',
        'complement' => 'Apto 1',
        'city' => 'Guarabira',
        'state' => 'PB',
    ];

    $card = AsaasService::buildCardData([
        'holder_name' => 'LUCAS B BRITO',
        'number' => '4242424242424242',
        'expiry_month' => '12',
        'expiry_year' => '2027',
        'ccv' => '123',
        'holder_cpf' => '11253197474',
        'holder_phone' => '83988849228',
    ], $order, useShippingAsBilling: true);

    expect($card['holder_postal_code'])->toBe('58200-230')
        ->and($card['holder_address_number'])->toBe('701')
        ->and($card['holder_address'])->toBe('Rua José Bonifácio')
        ->and($card['holder_district'])->toBe('Juá')
        ->and($card['holder_complement'])->toBe('Apto 1');
});
