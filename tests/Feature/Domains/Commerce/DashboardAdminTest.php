<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('exige autenticação no dashboard admin', function () {
    $this->getJson('/api/admin/dashboard')->assertUnauthorized();
});

it('admin obtém resumo do dashboard', function () {
    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->getJson('/api/admin/dashboard');

    $res->assertOk()
        ->assertJsonStructure([
            'orders_by_status',
            'orders_total',
            'recent_orders',
            'products_total',
            'products_published',
            'reviews_pending',
        ]);
});
