<?php

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Models\User;
use App\Domains\Catalog\Models\Product;
use App\Domains\Storage\Services\R2PresignService;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->seed(RolesPermissionSeeder::class);
    $this->admin = User::factory()->create();
    $this->admin->assignRole('admin');
});

it('returns presigned url for authenticated admin', function () {
    $product = Product::create([
        'title'  => 'Test',
        'slug'   => 'test-presign-' . Str::random(6),
        'origin' => 'national',
        'status' => 'draft',
    ]);

    $this->mock(R2PresignService::class, function ($mock) use ($product) {
        $mock->shouldReceive('generatePutUrl')
            ->once()
            ->withArgs(fn ($key) => str_starts_with($key, "products/{$product->id}/") && str_ends_with($key, '.jpg'))
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
        'slug'   => 'test-pres-mime-' . Str::random(6),
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

it('rejects mime/ext mismatch', function () {
    $product = Product::create([
        'title'  => 'Test',
        'slug'   => 'test-pres-mismatch-' . Str::random(6),
        'origin' => 'national',
        'status' => 'draft',
    ]);

    $this->withHeaders(jwtHeaders($this->admin))
        ->postJson(
            "/api/admin/products/{$product->id}/uploads/presign",
            ['mime' => 'image/jpeg', 'size' => 1024, 'ext' => 'png']
        )
        ->assertUnprocessable()
        ->assertJsonPath('errors.ext.0', 'The extension does not match the declared MIME type.');
});

it('rejects unauthenticated request', function () {
    $product = Product::create([
        'title'  => 'Test',
        'slug'   => 'test-pres-unauth-' . Str::random(6),
        'origin' => 'national',
        'status' => 'draft',
    ]);

    $this->postJson(
        "/api/admin/products/{$product->id}/uploads/presign",
        ['mime' => 'image/jpeg', 'size' => 1024, 'ext' => 'jpg']
    )->assertUnauthorized();
});
