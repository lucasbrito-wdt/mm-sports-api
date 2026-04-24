<?php

namespace App\Domains\Catalog\Enums;

enum ProductOrigin: string
{
    case National = 'national';
    case Imported = 'imported';
}
