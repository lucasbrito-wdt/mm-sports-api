<?php

use App\Domains\Commerce\Models\Order;
use App\Domains\Integrations\Services\AsaasService;
use Tests\TestCase;

uses(TestCase::class);

beforeEach(function () {
    $this->order = Mockery::mock(Order::class);
    $this->order->shouldReceive('getAttribute')->with('id')->andReturn('01HZ000000000000000000001');
    $this->order->shouldReceive('getAttribute')->with('grand_total')->andReturn(150.00);
    $this->order->shouldReceive('getAttribute')->with('guest_name')->andReturn('João Silva');
    $this->order->shouldReceive('getAttribute')->with('guest_email')->andReturn('joao@example.com');
    $this->order->shouldReceive('getAttribute')->with('guest_cpf')->andReturn('12345678901');
    $this->order->shouldReceive('getAttribute')->with('guest_phone')->andReturn('11987654321');
    $this->order->shouldReceive('getAttribute')->with('asaas_customer_id')->andReturn(null);
    $this->service = new AsaasService();
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

    expect($result)->toHaveKey('status');
});
