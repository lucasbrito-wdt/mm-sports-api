<?php

namespace App\Domains\Storage\Services;

use Aws\S3\S3Client;

class R2PresignService
{
    private const TTL_SECONDS = 300;

    /**
     * Generate a presigned PUT URL for direct browser-to-R2 upload.
     *
     * @return array{presigned_url:string, public_url:string, key:string, expires_in:int}
     */
    public function generatePutUrl(string $key, string $contentType, int $contentLength): array
    {
        $client = new S3Client([
            'region'                  => 'auto',
            'version'                 => 'latest',
            'endpoint'                => config('filesystems.disks.r2.endpoint'),
            'use_path_style_endpoint' => true,
            'credentials'             => [
                'key'    => config('filesystems.disks.r2.key'),
                'secret' => config('filesystems.disks.r2.secret'),
            ],
        ]);

        $bucket    = config('filesystems.disks.r2.bucket');
        $publicBase = rtrim((string) config('filesystems.disks.r2.url'), '/');

        $cmd = $client->getCommand('PutObject', [
            'Bucket'        => $bucket,
            'Key'           => $key,
            'ContentType'   => $contentType,
            'ContentLength' => $contentLength,
        ]);

        $request = $client->createPresignedRequest($cmd, '+' . self::TTL_SECONDS . ' seconds');

        return [
            'presigned_url' => (string) $request->getUri(),
            'public_url'    => $publicBase . '/' . ltrim($key, '/'),
            'key'           => $key,
            'expires_in'    => self::TTL_SECONDS,
        ];
    }
}
