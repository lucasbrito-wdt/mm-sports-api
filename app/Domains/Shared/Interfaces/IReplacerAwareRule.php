<?php

namespace App\Domains\Shared\Interfaces;

interface IReplacerAwareRule
{
    public function replacers(): array;
}
