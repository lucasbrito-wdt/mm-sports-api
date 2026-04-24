<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('adds attribute_value_ids and search_tsv to products', function () {
    expect(Schema::hasColumn('products', 'attribute_value_ids'))->toBeTrue();
    expect(Schema::hasColumn('products', 'search_tsv'))->toBeTrue();
});

it('adds attribute_value_ids to product_variants', function () {
    expect(Schema::hasColumn('product_variants', 'attribute_value_ids'))->toBeTrue();
});

it('creates GIN indexes', function () {
    $idx = collect(DB::select(
        "SELECT indexname FROM pg_indexes WHERE schemaname='public'
         AND indexname IN ('products_attrs_gin','products_search_gin','products_title_trgm','pv_attrs_gin','pv_payload_gin')"
    ))->pluck('indexname')->all();

    expect($idx)->toContain(
        'products_attrs_gin', 'products_search_gin', 'products_title_trgm',
        'pv_attrs_gin', 'pv_payload_gin'
    );
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'Requires PostgreSQL');
