<?php

use Illuminate\Support\Facades\DB;

it('creates product_facet_counts materialized view with unique index', function () {
    $mv = DB::selectOne(
        "SELECT matviewname FROM pg_matviews
         WHERE schemaname='public' AND matviewname='product_facet_counts'"
    );
    expect($mv)->not->toBeNull();

    $idx = DB::selectOne(
        "SELECT indexname FROM pg_indexes
         WHERE schemaname='public' AND indexname='product_facet_counts_pk'"
    );
    expect($idx)->not->toBeNull();
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'Requires PostgreSQL');
