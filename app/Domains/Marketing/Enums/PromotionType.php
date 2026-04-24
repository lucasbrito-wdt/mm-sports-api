<?php

namespace App\Domains\Marketing\Enums;

enum PromotionType: string
{
    case Percent = 'percent';
    case FixedAmount = 'fixed_amount';
}
