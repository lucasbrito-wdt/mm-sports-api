<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Commerce\Models\UserAddress;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->customerUser = User::factory()->create();
    $this->customerUser->assignRole('user');
});

it('lista endereços do cliente na rota admin', function () {
    UserAddress::query()->create([
        'user_id' => $this->customerUser->id,
        'recipient_name' => 'Maria Entrega',
        'postal_code' => '01310100',
        'street' => 'Av. Paulista',
        'number' => '500',
        'complement' => null,
        'district' => 'Bela Vista',
        'city' => 'São Paulo',
        'state' => 'SP',
        'is_default' => true,
    ]);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/customers/'.$this->customerUser->id.'/addresses');

    $res->assertOk();
    $res->assertJsonPath('data.0.recipient_name', 'Maria Entrega');
    $res->assertJsonPath('data.0.user_id', (string) $this->customerUser->id);
});

it('cria endereço para o cliente na rota admin', function () {
    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/customers/'.$this->customerUser->id.'/addresses', [
            'recipient_name' => 'João Recebe',
            'postal_code' => '01310100',
            'street' => 'Rua X',
            'number' => '10',
            'complement' => 'Apto 2',
            'district' => 'Centro',
            'city' => 'São Paulo',
            'state' => 'sp',
            'is_default' => true,
        ]);

    $res->assertCreated();
    $res->assertJsonPath('data.recipient_name', 'João Recebe');
    $res->assertJsonPath('data.state', 'SP');
    expect(UserAddress::query()->where('user_id', $this->customerUser->id)->count())->toBe(1);
});

it('atualiza endereço do cliente na rota admin', function () {
    $addr = UserAddress::query()->create([
        'user_id' => $this->customerUser->id,
        'recipient_name' => 'Antigo',
        'postal_code' => '01310100',
        'street' => 'Rua A',
        'number' => '1',
        'complement' => null,
        'district' => 'Bairro',
        'city' => 'São Paulo',
        'state' => 'SP',
        'is_default' => true,
    ]);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->putJson('/api/admin/customers/'.$this->customerUser->id.'/addresses/'.$addr->id, [
            'recipient_name' => 'Novo nome',
            'street' => 'Rua B',
        ]);

    $res->assertOk();
    $res->assertJsonPath('data.recipient_name', 'Novo nome');
    $res->assertJsonPath('data.street', 'Rua B');
    expect($addr->fresh()->recipient_name)->toBe('Novo nome');
});

it('remove endereço do cliente na rota admin', function () {
    $addr = UserAddress::query()->create([
        'user_id' => $this->customerUser->id,
        'recipient_name' => 'X',
        'postal_code' => '01310100',
        'street' => 'Rua A',
        'number' => '1',
        'complement' => null,
        'district' => 'Bairro',
        'city' => 'São Paulo',
        'state' => 'SP',
        'is_default' => false,
    ]);

    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->deleteJson('/api/admin/customers/'.$this->customerUser->id.'/addresses/'.$addr->id);

    $res->assertOk();
    $res->assertJsonPath('ok', true);
    expect(UserAddress::query()->whereKey($addr->id)->exists())->toBeFalse();
});
