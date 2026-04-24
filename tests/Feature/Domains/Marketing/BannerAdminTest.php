<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Marketing\Models\Banner;
use App\Domains\Tracking\Models\AuditLog;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
    $this->user = User::factory()->create();
    $this->user->assignRole('user');
});

it('exige autenticação na admin de banners', function () {
    $this->getJson('/api/admin/banners')->assertUnauthorized();
});

it('rejeita utilizador sem permissão de banners', function () {
    $this->withHeaders(jwtHeaders($this->user))
        ->getJson('/api/admin/banners')
        ->assertForbidden();
});

it('admin cria banner e gera audit', function () {
    $payload = [
        'internal_title' => 'Campanha A',
        'image_url' => 'https://cdn.example/b.jpg',
        'destination_url' => 'https://mm.example/shop',
        'sort_order' => 1,
    ];
    $res = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson('/api/admin/banners', $payload);
    $res->assertCreated();
    $id = $res->json('id');
    expect(Banner::query()->whereKey($id)->exists())->toBeTrue();
    expect(
        AuditLog::query()
            ->where('auditable_id', (string) $id)
            ->where('action', 'banners.create')
            ->exists()
    )->toBeTrue();
});

it('admin atualiza e remove banner com audit', function () {
    $banner = Banner::query()->create([
        'internal_title' => 'X',
        'image_url' => 'https://a.test/i.png',
        'destination_url' => 'https://a.test',
        'sort_order' => 0,
        'is_active' => true,
        'starts_at' => null,
        'ends_at' => null,
        'device' => null,
    ]);
    $id = (string) $banner->id;

    $this->withHeaders(jwtHeaders($this->admin))
        ->putJson("/api/admin/banners/{$id}", ['internal_title' => 'X2'])
        ->assertOk();
    $banner->refresh();
    expect($banner->internal_title)->toBe('X2');
    expect(
        AuditLog::query()
            ->where('auditable_id', $id)
            ->where('action', 'banners.update')
            ->exists()
    )->toBeTrue();

    $this->withHeaders(jwtHeaders($this->admin))
        ->deleteJson("/api/admin/banners/{$id}")
        ->assertNoContent();
    expect(Banner::query()->find($id))->toBeNull();
    expect(
        AuditLog::query()
            ->where('auditable_id', $id)
            ->where('action', 'banners.delete')
            ->exists()
    )->toBeTrue();
});
