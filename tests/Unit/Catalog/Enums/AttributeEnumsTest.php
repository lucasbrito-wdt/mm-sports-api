<?php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;

it('exposes attribute types', function () {
    expect(AttributeType::Facet->value)->toBe('facet');
    expect(AttributeType::Variant->value)->toBe('variant');
    expect(AttributeType::Both->value)->toBe('both');
});

it('exposes attribute input types', function () {
    expect(AttributeInputType::Select->value)->toBe('select');
    expect(AttributeInputType::Multiselect->value)->toBe('multiselect');
    expect(AttributeInputType::Text->value)->toBe('text');
    expect(AttributeInputType::Swatch->value)->toBe('swatch');
});
