<?php

namespace App\Domains\Marketing\Services;

use RuntimeException;

class CouponValidationException extends RuntimeException
{
    public function __construct(string $message, public readonly string $reason)
    {
        parent::__construct($message);
    }
}
