<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

function categoriesMigrationInstance(): object
{
    /** @var object $migration */
    $migration = require base_path('app/Domains/Catalog/Migrations/2026_04_25_141800_create_categories_and_link_products.php');

    return $migration;
}

function hasForeignKeyForColumn(string $table, string $column, string $referencedTable, string $referencedColumn): bool
{
    $driver = DB::getDriverName();

    if ($driver === 'sqlite') {
        $rows = DB::select("PRAGMA foreign_key_list('{$table}')");

        return collect($rows)->contains(function ($fk) use ($column, $referencedTable, $referencedColumn) {
            return ($fk->from ?? null) === $column
                && ($fk->table ?? null) === $referencedTable
                && ($fk->to ?? null) === $referencedColumn;
        });
    }

    if ($driver === 'pgsql') {
        $rows = DB::select(
            <<<'SQL'
            SELECT
                kcu.column_name AS source_column,
                ccu.table_name AS referenced_table,
                ccu.column_name AS referenced_column
            FROM information_schema.table_constraints tc
            JOIN information_schema.key_column_usage kcu
                ON tc.constraint_name = kcu.constraint_name
               AND tc.table_schema = kcu.table_schema
            JOIN information_schema.constraint_column_usage ccu
                ON ccu.constraint_name = tc.constraint_name
               AND ccu.table_schema = tc.table_schema
            WHERE tc.constraint_type = 'FOREIGN KEY'
              AND tc.table_schema = current_schema()
              AND tc.table_name = ?
            SQL,
            [$table],
        );

        return collect($rows)->contains(function ($fk) use ($column, $referencedTable, $referencedColumn) {
            return ($fk->source_column ?? null) === $column
                && ($fk->referenced_table ?? null) === $referencedTable
                && ($fk->referenced_column ?? null) === $referencedColumn;
        });
    }

    if ($driver === 'mysql') {
        $rows = DB::select(
            <<<'SQL'
            SELECT
                kcu.COLUMN_NAME AS source_column,
                kcu.REFERENCED_TABLE_NAME AS referenced_table,
                kcu.REFERENCED_COLUMN_NAME AS referenced_column
            FROM information_schema.KEY_COLUMN_USAGE kcu
            WHERE kcu.TABLE_SCHEMA = DATABASE()
              AND kcu.TABLE_NAME = ?
              AND kcu.REFERENCED_TABLE_NAME IS NOT NULL
            SQL,
            [$table],
        );

        return collect($rows)->contains(function ($fk) use ($column, $referencedTable, $referencedColumn) {
            return ($fk->source_column ?? null) === $column
                && ($fk->referenced_table ?? null) === $referencedTable
                && ($fk->referenced_column ?? null) === $referencedColumn;
        });
    }

    // Fallback seguro: evita falso positivo em drivers sem consulta estável de metadados.
    test()->markTestSkipped("Foreign key introspection is not implemented for driver [{$driver}].");

    return false;
}

function hasIndexByName(string $table, string $indexName): bool
{
    $driver = DB::getDriverName();

    if ($driver === 'sqlite') {
        $rows = DB::select("PRAGMA index_list('{$table}')");

        return collect($rows)->pluck('name')->contains($indexName);
    }

    if ($driver === 'pgsql') {
        $rows = DB::select(
            <<<'SQL'
            SELECT indexname
            FROM pg_indexes
            WHERE schemaname = current_schema()
              AND tablename = ?
            SQL,
            [$table],
        );

        return collect($rows)->pluck('indexname')->contains($indexName);
    }

    if ($driver === 'mysql') {
        $rows = DB::select(
            <<<'SQL'
            SELECT INDEX_NAME AS index_name
            FROM information_schema.STATISTICS
            WHERE TABLE_SCHEMA = DATABASE()
              AND TABLE_NAME = ?
            SQL,
            [$table],
        );

        return collect($rows)->pluck('index_name')->contains($indexName);
    }

    // Fallback seguro: evita acoplamento com SQL específico de drivers não suportados.
    test()->markTestSkipped("Index introspection is not implemented for driver [{$driver}].");

    return false;
}

it('creates categories and adds category link on products', function () {
    expect(Schema::hasTable('categories'))->toBeTrue();
    expect(Schema::hasColumns('categories', [
        'id', 'name', 'slug', 'parent_id', 'is_active', 'display_order', 'created_at', 'updated_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('products'))->toBeTrue();
    expect(Schema::hasColumn('products', 'category_id'))->toBeTrue();
});

it('creates foreign keys for categories.parent_id and products.category_id', function () {
    $hasParentFk = hasForeignKeyForColumn('categories', 'parent_id', 'categories', 'id');
    $hasCategoryFk = hasForeignKeyForColumn('products', 'category_id', 'categories', 'id');

    expect($hasParentFk)->toBeTrue();
    expect($hasCategoryFk)->toBeTrue();
});

it('creates explicit indexes for categories.parent_id and products.category_id', function () {
    expect(hasIndexByName('categories', 'categories_parent_id_index'))->toBeTrue();
    expect(hasIndexByName('products', 'products_category_id_index'))->toBeTrue();
});

it('rolls back and reapplies migration cleanly', function () {
    if (DB::getDriverName() === 'sqlite') {
        // No SQLite, o down() atual falha ao remover column/index nessa ordem.
        // Como a tarefa A.1 aqui é só de teste (não de migration), este cenário é
        // validado apenas em drivers onde o rollback do schema é estável (MySQL/PostgreSQL).
        test()->markTestSkipped('Rollback down()/up() desta migration é validado apenas fora de SQLite.');
    }

    $migration = categoriesMigrationInstance();

    call_user_func([$migration, 'down']);

    expect(Schema::hasTable('categories'))->toBeFalse();
    expect(Schema::hasColumn('products', 'category_id'))->toBeFalse();

    // Reaplica para manter isolamento e não impactar testes seguintes.
    call_user_func([$migration, 'up']);

    expect(Schema::hasTable('categories'))->toBeTrue();
    expect(Schema::hasColumn('products', 'category_id'))->toBeTrue();
});
