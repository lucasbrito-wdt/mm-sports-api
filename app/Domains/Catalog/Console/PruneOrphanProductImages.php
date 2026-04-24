<?php

namespace App\Domains\Catalog\Console;

use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class PruneOrphanProductImages extends Command
{
    protected $signature = 'catalog:prune-orphan-product-images {--dry-run}';
    protected $description = 'Remove R2 objects under products/ that have no matching product_images.url';

    public function handle(): int
    {
        $disk = Storage::disk('r2');
        $publicBase = rtrim((string) config('filesystems.disks.r2.url'), '/');
        $oneDayAgo = now()->subDay();

        $registeredKeys = ProductImage::query()
            ->where('url', 'like', $publicBase . '/products/%')
            ->pluck('url')
            ->map(fn ($url) => ltrim((string) parse_url((string) $url, PHP_URL_PATH), '/'))
            ->all();
        $registered = array_flip($registeredKeys);

        $deleted = 0;
        foreach ($disk->allFiles('products') as $key) {
            if (isset($registered[$key])) {
                continue;
            }
            $lastModified = $disk->lastModified($key);
            if ($lastModified && $lastModified > $oneDayAgo->timestamp) {
                continue;
            }
            if ($this->option('dry-run')) {
                $this->line("would delete: {$key}");
            } else {
                $disk->delete($key);
                $this->line("deleted: {$key}");
            }
            $deleted++;
        }

        $verb = $this->option('dry-run') ? 'Would prune' : 'Pruned';
        $this->info("{$verb} {$deleted} orphan object(s).");
        return self::SUCCESS;
    }
}
