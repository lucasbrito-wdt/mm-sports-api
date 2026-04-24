<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Models\Product;
use App\Domains\Storage\Services\R2PresignService;

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('returns presigned url for authenticated admin', function () {
    $product = Product::create([
        'title'  => 'Test',
        'slug'   => 'test-presign',
        'origin' => 'national',
        'status' => 'draft',
    ]);

    $this->mock(R2PresignService::class, function ($mock) use ($product) {
        $mock->shouldReceive('generatePutUrl')
            ->once()
            ->andReturn([
                'presigned_url' => 'https://r2.example.com/signed',
                'public_url'    => "https://cdn.example.com/products/{$product->id}/abc.jpg",
                'key'           => "products/{$product->id}/abc.jpg",
                'expires_in'    => 300,
            ]);
    });

    $response = $this->withHeaders(jwtHeaders($this->admin))
        ->postJson(
            "/api/admin/products/{$product->id}/uploads/presign",
            ['mime' => 'image/jpeg', 'size' => 1024, 'ext' => 'jpg']
        );

    $response->assertOk()
        ->assertJsonStructure(['data' => ['presigned_url', 'public_url', 'key', 'expires_in']]);

    expect($response->json('data.key'))->toContain("products/{$product->id}/");
});

it('rejects invalid mime type', function () {
    $product = Product::create([
        'title'  => 'Test',
        'slug'   => 'test-pres-mime',
        'origin' => 'national',
        'status' => 'draft',
    ]);

    $this->withHeaders(jwtHeaders($this->admin))
        ->postJson(
            "/api/admin/products/{$product->id}/uploads/presign",
            ['mime' => 'application/exe', 'size' => 1024, 'ext' => 'exe']
        )
        ->assertUnprocessable();
});

it('rejects unauthenticated request', function () {
    $product = Product::create([
        'title'  => 'Test',
        'slug'   => 'test-pres-unauth',
        'origin' => 'national',
        'status' => 'draft',
    ]);

    $this->postJson(
        "/api/admin/products/{$product->id}/uploads/presign",
        ['mime' => 'image/jpeg', 'size' => 1024, 'ext' => 'jpg']
    )->assertUnauthorized();
});
