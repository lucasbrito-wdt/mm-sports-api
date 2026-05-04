<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement("
            CREATE MATERIALIZED VIEW product_facet_counts AS
            SELECT av.attribute_id,
                   av.id AS attribute_value_id,
                   COUNT(DISTINCT p.id) AS product_count
            FROM attribute_values av
            JOIN product_attribute_values pav
                 ON pav.attribute_value_id = av.id
            JOIN products p
                 ON p.id = pav.product_id
                 AND p.status = 'published'
            GROUP BY 1, 2
            WITH NO DATA
        ");

        DB::statement('
            CREATE UNIQUE INDEX product_facet_counts_pk
            ON product_facet_counts (attribute_id, attribute_value_id)
        ');

        DB::statement('REFRESH MATERIALIZED VIEW product_facet_counts');
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP MATERIALIZED VIEW IF EXISTS product_facet_counts');
    }
};
