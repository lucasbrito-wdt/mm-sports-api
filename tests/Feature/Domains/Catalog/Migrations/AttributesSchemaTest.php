<?php

use Illuminate\Support\Facades\Schema;

it('creates attributes table with expected columns', function () {
    expect(Schema::hasTable('attributes'))->toBeTrue();
    expect(Schema::hasColumns('attributes', [
        'id', 'code', 'label', 'type', 'input_type',
        'is_filterable', 'display_order', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('creates attribute_values table with unique (attribute_id, slug)', function () {
    expect(Schema::hasTable('attribute_values'))->toBeTrue();
    expect(Schema::hasColumns('attribute_values', [
        'id', 'attribute_id', 'value', 'slug', 'metadata',
        'display_order', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
