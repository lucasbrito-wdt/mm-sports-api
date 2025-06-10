<?php

namespace App\Domains\Shared\Traits;

use Illuminate\Support\Str;

trait HasUlid
{
    /**
     * Indica que a chave primária não é incremental.
     */
    public $incrementing = false;

    /**
     * Define o tipo da chave primária.
     */
    protected $keyType = 'string';

    /**
     * Boot the trait.
     */
    protected static function bootHasUlid()
    {
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = Str::ulid();
            }
        });
    }
}
