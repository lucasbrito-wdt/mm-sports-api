<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE products
                ADD COLUMN attribute_value_ids text[] NOT NULL DEFAULT '{}',
                ADD COLUMN search_tsv tsvector
                    GENERATED ALWAYS AS (
                        to_tsvector('portuguese',
                            coalesce(title,'') || ' ' || coalesce(description,''))
                    ) STORED");

            DB::statement('CREATE INDEX products_attrs_gin
                ON products USING GIN (attribute_value_ids)');

            DB::statement('CREATE INDEX products_search_gin
                ON products USING GIN (search_tsv)');

            DB::statement('CREATE INDEX products_title_trgm
                ON products USING GIN (title gin_trgm_ops)');

            DB::statement("ALTER TABLE product_variants
                ADD COLUMN attribute_value_ids text[] NOT NULL DEFAULT '{}'");

            DB::statement('CREATE INDEX pv_attrs_gin
                ON product_variants USING GIN (attribute_value_ids)');

            DB::statement('CREATE INDEX pv_payload_gin
                ON product_variants USING GIN ((attribute_payload::jsonb) jsonb_path_ops)');

            return;
        }

        Schema::table('products', function (Blueprint $table) {
            $table->json('attribute_value_ids')->nullable();
            $table->text('search_tsv')->nullable();
        });

        Schema::table('product_variants', function (Blueprint $table) {
            $table->json('attribute_value_ids')->nullable();
        });
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP INDEX IF EXISTS pv_payload_gin');
            DB::statement('DROP INDEX IF EXISTS pv_attrs_gin');
            DB::statement('DROP INDEX IF EXISTS products_title_trgm');
            DB::statement('DROP INDEX IF EXISTS products_search_gin');
            DB::statement('DROP INDEX IF EXISTS products_attrs_gin');

            DB::statement('ALTER TABLE product_variants DROP COLUMN IF EXISTS attribute_value_ids');
            DB::statement('ALTER TABLE products
                DROP COLUMN IF EXISTS search_tsv,
                DROP COLUMN IF EXISTS attribute_value_ids');

            return;
        }

        Schema::table('product_variants', function (Blueprint $table) {
            $table->dropColumn('attribute_value_ids');
        });

        Schema::table('products', function (Blueprint $table) {
            $table->dropColumn(['search_tsv', 'attribute_value_ids']);
        });
    }
};
