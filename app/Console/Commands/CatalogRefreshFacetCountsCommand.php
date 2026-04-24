<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CatalogRefreshFacetCountsCommand extends Command
{
    protected $signature = 'catalog:refresh-facets';

    protected $description = 'Atualiza a materialized view product_facet_counts (somente PostgreSQL)';

    public function handle(): int
    {
        if (DB::getDriverName() !== 'pgsql') {
            $this->warn('Materialized view só existe em PostgreSQL; nada a fazer.');

            return self::SUCCESS;
        }

        if (DB::transactionLevel() > 0) {
            DB::statement('REFRESH MATERIALIZED VIEW product_facet_counts');
            $this->info('MV atualizada (modo não concorrente — transação ativa).');

            return self::SUCCESS;
        }

        try {
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY product_facet_counts');
        } catch (\Throwable $e) {
            DB::statement('REFRESH MATERIALIZED VIEW product_facet_counts');
            $this->warn('CONCURRENTLY falhou; usado refresh simples: '.$e->getMessage());
        }

        $this->info('product_facet_counts atualizada.');

        return self::SUCCESS;
    }
}
