<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('creates attribute + values via admin API', function () {
    $attr = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/attributes', [
            'code' => 'collar',
            'label' => 'Gola',
            'type' => 'facet',
            'input_type' => 'select',
        ])
        ->assertCreated()
        ->json('data');

    $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/attributes/'.$attr['id'].'/values', [
            'value' => 'Polo',
        ])
        ->assertCreated()
        ->assertJsonPath('data.slug', 'polo');
});
