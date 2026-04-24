<?php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

it('casts enums and has values relation', function () {
    $attr = Attribute::create([
        'code' => 'color',
        'label' => 'Cor',
        'type' => AttributeType::Variant,
        'input_type' => AttributeInputType::Swatch,
        'is_filterable' => true,
        'display_order' => 1,
    ]);

    expect($attr->type)->toBe(AttributeType::Variant);
    expect($attr->input_type)->toBe(AttributeInputType::Swatch);
    expect($attr->values())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});
