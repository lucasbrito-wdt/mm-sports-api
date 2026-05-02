<?php

use App\Domains\Commerce\Requests\GuestOrderRequest;
use Illuminate\Support\Facades\Validator;

uses(Tests\TestCase::class);

test('GuestOrderRequest fails when required fields are missing', function () {
    $validator = Validator::make([], (new GuestOrderRequest())->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('billing_type'))->toBeTrue();
    expect($validator->errors()->has('customer.name'))->toBeTrue();
    expect($validator->errors()->has('items'))->toBeTrue();
});

test('GuestOrderRequest passes valid PIX payload', function () {
    $payload = [
        'billing_type' => 'PIX',
        'customer' => ['name' => 'João', 'email' => 'j@j.com', 'cpf' => '12345678901', 'phone' => '11999999999'],
        'address' => ['postal_code' => '01310100', 'street' => 'Av X', 'number' => '1', 'complement' => '', 'city' => 'SP', 'state' => 'SP'],
        'items' => [['product_variant_id' => '01HZ000', 'quantity' => 1]],
    ];
    $validator = Validator::make($payload, (new GuestOrderRequest())->rules());
    expect($validator->fails())->toBeFalse();
});

test('GuestOrderRequest requires credit_card when billing_type is CREDIT_CARD', function () {
    $payload = [
        'billing_type' => 'CREDIT_CARD',
        'customer' => ['name' => 'João', 'email' => 'j@j.com', 'cpf' => '12345678901', 'phone' => '11999999999'],
        'address' => ['postal_code' => '01310100', 'street' => 'Av X', 'number' => '1', 'complement' => '', 'city' => 'SP', 'state' => 'SP'],
        'items' => [['product_variant_id' => '01HZ000', 'quantity' => 1]],
    ];

    $request = GuestOrderRequest::createFrom(
        \Illuminate\Http\Request::create('/', 'POST', $payload)
    );

    $validator = Validator::make($payload, $request->rules());
    expect($validator->fails())->toBeTrue();
    expect($validator->errors()->has('credit_card'))->toBeTrue();
});
