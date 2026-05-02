<?php

use App\Domains\Commerce\Services\OrderService;

test('createFromGuest throws InvalidArgumentException for empty items', function () {
    $service = app(OrderService::class);

    $customerData = [
        'name' => 'Maria Souza',
        'email' => 'maria@example.com',
        'cpf' => '98765432100',
        'phone' => '21987654321',
    ];

    $data = [
        'items' => [],
        'address' => [
            'postal_code' => '01310100',
            'street' => 'Av. Paulista',
            'number' => '1000',
            'complement' => 'Apto 1',
            'city' => 'São Paulo',
            'state' => 'SP',
        ],
        'billing_type' => 'PIX',
        'notes' => 'Deixar na portaria',
    ];

    expect(fn () => $service->createFromGuest($customerData, $data))
        ->toThrow(\InvalidArgumentException::class);
});
