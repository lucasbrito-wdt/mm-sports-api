<?php

namespace App\Domains\Catalog\Enums;

enum AttributeType: string
{
    case Facet = 'facet';
    case Variant = 'variant';
    case Both = 'both';

    public function isFacet(): bool
    {
        return in_array($this, [self::Facet, self::Both], true);
    }

    public function isVariant(): bool
    {
        return in_array($this, [self::Variant, self::Both], true);
    }
}
