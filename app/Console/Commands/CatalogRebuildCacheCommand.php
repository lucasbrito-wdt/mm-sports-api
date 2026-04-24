<?php

namespace App\Console\Commands;

use App\Domains\Catalog\Services\RebuildCatalogCacheService;
use Illuminate\Console\Command;

class CatalogRebuildCacheCommand extends Command
{
    protected $signature = 'catalog:rebuild-cache';

    protected $description = 'Reidrata caches denormalizados de atributos e atualiza contagens de facetas (Postgres)';

    public function handle(RebuildCatalogCacheService $rebuildCatalogCacheService): int
    {
        $this->info('Reconstruindo caches do catálogo...');
        $rebuildCatalogCacheService->handle();
        $this->info('Concluído.');

        return self::SUCCESS;
    }
}
