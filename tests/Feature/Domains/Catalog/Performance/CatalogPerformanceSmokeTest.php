<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('uses GIN on products.attribute_value_ids containment filter', function () {
    if (DB::getDriverName() !== 'pgsql' || ! Schema::hasColumn('products', 'attribute_value_ids')) {
        expect(true)->toBeTrue();

        return;
    }

    DB::transaction(function () {
        DB::statement('SET LOCAL enable_seqscan TO off');

        $rows = DB::select("
            EXPLAIN (FORMAT JSON)
            SELECT id FROM products
            WHERE attribute_value_ids @> ARRAY['01JFBZZZZZZZZZZZZZZZZZZZZ']::text[]
        ");

        expect($rows)->not->toBeEmpty();

        $planText = json_encode($rows[0], JSON_THROW_ON_ERROR);

        expect($planText)->toMatch('/products_attrs_gin/i')
            ->and($planText)->toMatch('/Bitmap|bitmap|Index\\s+Scan/i');
    });
})->group('performance');
