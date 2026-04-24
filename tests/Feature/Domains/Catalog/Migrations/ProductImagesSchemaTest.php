<?php

use Illuminate\Support\Facades\Schema;

it('creates product_images with attribute_value_id nullable', function () {
    expect(Schema::hasTable('product_images'))->toBeTrue();
    expect(Schema::hasColumns('product_images', [
        'id', 'product_id', 'attribute_value_id',
        'url', 'alt', 'display_order', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
