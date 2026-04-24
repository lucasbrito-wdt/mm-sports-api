<?php

use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Seeders\AttributeSeeder;
use App\Domains\Catalog\Seeders\AttributeValueSeeder;

it('seeds 12 attributes classified per spec', function () {
    (new AttributeSeeder)->run();
    (new AttributeValueSeeder)->run();

    expect(Attribute::count())->toBe(12);

    expect(Attribute::where('code', 'color')->first()->type)->toBe(AttributeType::Variant);
    expect(Attribute::where('code', 'size')->first()->type)->toBe(AttributeType::Variant);
    expect(Attribute::where('code', 'brand')->first()->type)->toBe(AttributeType::Facet);

    $color = Attribute::where('code', 'color')->first();
    expect($color->values()->count())->toBeGreaterThan(3);
});
