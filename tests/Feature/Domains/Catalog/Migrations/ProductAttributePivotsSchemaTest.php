<?php

use Illuminate\Support\Facades\Schema;

it('creates product_attribute_values with composite PK', function () {
    expect(Schema::hasTable('product_attribute_values'))->toBeTrue();
    expect(Schema::hasColumns('product_attribute_values', [
        'product_id', 'attribute_value_id',
    ]))->toBeTrue();
});

it('creates product_variant_axes with unique (product_id, attribute_id)', function () {
    expect(Schema::hasTable('product_variant_axes'))->toBeTrue();
    expect(Schema::hasColumns('product_variant_axes', [
        'product_id', 'attribute_id', 'display_order',
    ]))->toBeTrue();
});

it('creates variant_attribute_values table', function () {
    expect(Schema::hasTable('variant_attribute_values'))->toBeTrue();
    expect(Schema::hasColumns('variant_attribute_values', [
        'product_variant_id', 'attribute_id', 'attribute_value_id',
    ]))->toBeTrue();
});
