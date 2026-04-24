<?php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('belongs to an attribute and casts metadata', function () {
    $attr = Attribute::create([
        'code' => 'color', 'label' => 'Cor',
        'type' => AttributeType::Variant,
        'input_type' => AttributeInputType::Swatch,
    ]);

    $value = AttributeValue::create([
        'attribute_id' => $attr->id,
        'value' => 'Azul',
        'slug' => 'azul',
        'metadata' => ['hex' => '#0000FF'],
        'display_order' => 1,
    ]);

    expect($value->attribute->code)->toBe('color');
    expect($value->metadata)->toBe(['hex' => '#0000FF']);
});
