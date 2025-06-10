<?php

namespace App\Domains\Shared\Utils;

class IntHelper
{
    public static function tryParser($value) {
        return ctype_digit($value) ? intval($value) : null;
    }
}
