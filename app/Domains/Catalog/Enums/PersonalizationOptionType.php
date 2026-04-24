<?php

namespace App\Domains\Catalog\Enums;

enum PersonalizationOptionType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case Select = 'select';
    case Number = 'number';
}
