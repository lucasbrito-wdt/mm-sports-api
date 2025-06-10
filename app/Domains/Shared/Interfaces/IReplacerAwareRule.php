<?php
namespace App\Domains\Shared\Interfaces;

interface ReplacerAwareRule
{
    public function replacers(): array;
}
