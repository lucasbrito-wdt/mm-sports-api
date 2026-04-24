<?php

namespace App\Domains\Catalog\Enums;

enum AttributeInputType: string
{
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Text = 'text';
    case Swatch = 'swatch';
}
