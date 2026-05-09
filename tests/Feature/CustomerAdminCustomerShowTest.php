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

it('inclui user_addresses no show de cliente admin', function () {
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
        ->getJson('/api/admin/customers/'.$this->customerUser->id);

    $res->assertOk();
    $res->assertJsonPath('user_addresses.0.recipient_name', 'Maria Entrega');
    $res->assertJsonPath('user_addresses.0.street', 'Av. Paulista');
    expect($res->json('orders_count'))->toBeInt();
});
