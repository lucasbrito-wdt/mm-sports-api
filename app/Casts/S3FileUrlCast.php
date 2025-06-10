<?php

namespace App\Casts;

use App\Domains\Shared\Traits\S3FileOperations;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class S3FileUrlCast implements CastsAttributes
{
    use S3FileOperations;

    /**
     * Cast the given value.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        // Se o valor for nulo ou vazio, retornar null explicitamente
        if (empty($value) || $value === '{}' || $value === '[]') {
            return null;
        }

        // Se o valor já for uma URL completa, retorná-lo diretamente
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            return $value;
        }

        // Se for um objeto ou array, provável erro de serialização, retornar null
        if (is_array($value) || is_object($value)) {
            return null;
        }

        // Se chegou aqui, temos um nome de arquivo, então obter a URL
        return $this->getS3FileUrl($value, $model->getTable());
    }

    /**
     * {@inheritDoc}
     */
    public function set(Model $model, string $key, mixed $value, array $attributes)
    {
        if (! is_string($value)) {
            return null;
        }
        $path = parse_url($value, PHP_URL_PATH);

        return basename($path);
    }
}
