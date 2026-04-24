<?php

namespace Tests\Unit\Storage;

use App\Domains\Storage\Services\R2PresignService;
use Aws\S3\S3Client;
use GuzzleHttp\Psr7\Uri;
use Illuminate\Support\Facades\Config;
use Mockery;
use Tests\TestCase;

class R2PresignServiceTest extends TestCase
{
    public function test_generates_signed_put_url_with_expected_shape(): void
    {
        Config::set('filesystems.disks.r2.bucket', 'test-bucket');
        Config::set('filesystems.disks.r2.url', 'https://cdn.test');

        // Mock S3Client
        $presignedUri = new Uri(
            'https://test-bucket.r2.cloudflarestorage.com/products/01H/abc.jpg'
            . '?X-Amz-Algorithm=AWS4-HMAC-SHA256&X-Amz-Signature=fakesig'
        );

        $psrRequest = Mockery::mock(\Psr\Http\Message\RequestInterface::class);
        $psrRequest->shouldReceive('getUri')->andReturn($presignedUri);

        $s3Client = Mockery::mock(S3Client::class);
        $s3Client->shouldReceive('getCommand')
            ->with('PutObject', Mockery::subset([
                'Bucket' => 'test-bucket',
                'Key' => 'products/01H/abc.jpg',
                'ContentType' => 'image/jpeg',
                'ContentLength' => 12345,
            ]))
            ->andReturn(Mockery::mock(\Aws\CommandInterface::class));
        $s3Client->shouldReceive('createPresignedRequest')
            ->andReturn($psrRequest);

        // Mock Storage facade
        $flysystemAdapter = Mockery::mock(\League\Flysystem\AwsS3V3\AwsS3V3Adapter::class);
        $flysystemAdapter->shouldReceive('getClient')->andReturn($s3Client);

        $laravelDisk = Mockery::mock(\Illuminate\Filesystem\FilesystemAdapter::class);
        $laravelDisk->shouldReceive('getAdapter')->andReturn($flysystemAdapter);

        \Illuminate\Support\Facades\Storage::shouldReceive('disk')
            ->with('r2')
            ->andReturn($laravelDisk);

        $service = new R2PresignService();
        $result = $service->generatePutUrl(
            key: 'products/01H/abc.jpg',
            contentType: 'image/jpeg',
            contentLength: 12345,
        );

        $this->assertArrayHasKey('presigned_url', $result);
        $this->assertArrayHasKey('public_url', $result);
        $this->assertArrayHasKey('expires_in', $result);
        $this->assertEquals(300, $result['expires_in']);
        $this->assertStringStartsWith('https://cdn.test/', $result['public_url']);
        $this->assertStringContainsString('products/01H/abc.jpg', $result['public_url']);
        $this->assertStringContainsString('X-Amz-Signature', $result['presigned_url']);
    }
}
