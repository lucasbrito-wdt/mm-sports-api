<?php

use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('cria endereço com CEP e UF válidos', function () {
    $user = User::factory()->create();
    $res = $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/user-addresses', [
            'recipient_name' => 'João',
            'postal_code' => '01310-100',
            'street' => 'Av. Paulista',
            'number' => '1000',
            'complement' => 'Sala 1',
            'district' => 'Bela Vista',
            'city' => 'São Paulo',
            'state' => 'sp',
            'is_default' => true,
        ]);
    $res->assertCreated();
    expect(UserAddress::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('rejeita UF com tamanho diferente de 2', function () {
    $user = User::factory()->create();
    $this->withHeaders(jwtHeaders($user))
        ->postJson('/api/user-addresses', [
            'recipient_name' => 'João',
            'postal_code' => '01310100',
            'street' => 'Rua A',
            'number' => '1',
            'district' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'São Paulo',
        ])
        ->assertUnprocessable();
});
