<?php

use Illuminate\Support\Facades\DB;

it('has required postgres extensions enabled', function () {
    $extensions = collect(DB::select(
        "SELECT extname FROM pg_extension WHERE extname IN ('pg_trgm','btree_gin','unaccent')"
    ))->pluck('extname')->all();

    expect($extensions)->toContain('pg_trgm', 'btree_gin', 'unaccent');
})->skip(fn () => DB::getDriverName() !== 'pgsql', 'Requires PostgreSQL (phpunit uses sqlite by default)');
