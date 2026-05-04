<?php

namespace App\Domains\Marketing\Enums;

enum CouponType: string
{
    case Percentage = 'percentage';
    case Fixed = 'fixed';
}
