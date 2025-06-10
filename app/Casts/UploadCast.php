<?php

namespace App\Casts;

use App\Domains\Shared\Traits\S3FileOperations;
use Exception;
use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class UploadCast implements CastsAttributes
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
     * Prepare the given value for storage.
     *
     * @param  array<string, mixed>  $attributes
     *
     * @throws Exception
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): ?string
    {
        $modelId = $attributes['id'] ?? 'sem-id';
        $modelTable = $model->getTable();
        Log::info("[UploadCast] Iniciando processamento para $modelTable/$modelId campo $key");

        // Se não houver ID ainda, não podemos processar o upload
        if (! isset($attributes['id'])) {
            Log::warning("[UploadCast] ID não disponível para $modelTable, retornando null");

            return null;
        }

        // Se o valor for null, mantemos null
        if (is_null($value)) {
            Log::info("[UploadCast] Valor null para $modelTable/$modelId campo $key");

            return null;
        }

        // Se for uma string e já for URL, extraímos apenas o nome do arquivo
        if (is_string($value) && filter_var($value, FILTER_VALIDATE_URL)) {
            Log::info("[UploadCast] Valor é URL para $modelTable/$modelId campo $key");

            // Extrai apenas o nome do arquivo da URL
            $parts = parse_url($value);
            $path = $parts['path'] ?? '';
            $basename = basename($path);

            Log::info("[UploadCast] Extraindo nome do arquivo da URL: $basename");

            return $basename;
        }

        // Se for uma string mas não for URL nem arquivo, retornamos como está
        if (is_string($value) && ! is_file($value)) {
            Log::info("[UploadCast] Valor é string não-arquivo para $modelTable/$modelId campo $key: $value");

            return $value;
        }

        // Chave de cache para evitar processamento duplicado
        // Incluir o nome do campo na chave de cache
        $cacheKey = "upload_{$modelTable}_{$modelId}_{$key}";

        // Verificar se já está em processamento
        if (Cache::has($cacheKey)) {
            Log::info("[UploadCast] Já existe processamento em andamento para $modelTable/$modelId campo $key");

            return $value;
        }

        // Marcar como em processamento por 5 minutos
        Cache::put($cacheKey, true, 300);
        Log::info("[UploadCast] Marcando processamento no cache: $cacheKey");

        // Processamento para upload de arquivos
        if (is_object($value) || is_file($value)) {
            // Incluir o nome do campo no nome do arquivo
            $fileName = "{$modelId}_{$key}";
            $fileType = is_object($value) ? 'objeto_upload' : 'arquivo_local';

            Log::info("[UploadCast] Iniciando upload de $fileType para $modelTable/$modelId campo $key");

            // Gerar o nome do arquivo
            $extension = is_object($value) ? $value->getClientOriginalExtension() : pathinfo($value, PATHINFO_EXTENSION);
            $tempFileName = $fileName . '.' . $extension;

            Log::info("[UploadCast] Nome temporário do arquivo: {$tempFileName}");

            // Realizar o upload diretamente (modo sync)
            try {
                Log::info("[UploadCast] Iniciando putS3FileIfNotExists para $modelTable/$modelId");

                $fileHash = $this->putS3FileIfNotExists(
                    file: $value,
                    path: $modelTable,
                    fileName: $fileName
                );

                if ($fileHash) {
                    Log::info("[UploadCast] Upload concluído com sucesso: $fileHash");

                    // Se o modelo não tiver sido salvo ainda, programamos para ser atualizado após salvar
                    if (! $model->exists) {
                        Log::info("[UploadCast] Modelo $modelTable/$modelId ainda não existe, programando atualização pós-save");

                        $model->saved(function ($savedModel) use ($key, $fileHash, $cacheKey, $modelTable, $modelId) {
                            Log::info("[UploadCast] Evento saved acionado para $modelTable/$modelId");

                            // Atualizar diretamente
                            $savedModel->withoutEvents(function () use ($savedModel, $key, $fileHash, $modelTable, $modelId) {
                                Log::info("[UploadCast] Atualizando modelo {$modelTable}/{$modelId} com fileHash: {$fileHash}");
                                $savedModel->forceFill([$key => $fileHash]);
                                $saveResult = $savedModel->save(['timestamps' => false]);
                                Log::info('[UploadCast] Resultado da atualização: ' . ($saveResult ? 'sucesso' : 'falha'));
                            });

                            // Remover cache após salvar
                            Cache::forget($cacheKey);
                            Log::info("[UploadCast] Cache removido: {$cacheKey}");
                        });
                    } else {
                        Log::info("[UploadCast] Modelo {$modelTable}/{$modelId} já existe, atualizando agora");

                        // Se o modelo já existe, atualizamos agora (sem eventos)
                        $model->withoutEvents(function () use ($model, $key, $fileHash, $modelTable, $modelId) {
                            Log::info("[UploadCast] Atualizando modelo existente {$modelTable}/{$modelId} com fileHash: {$fileHash}");
                            $model->forceFill([$key => $fileHash]);
                            $saveResult = $model->save(['timestamps' => false]);
                            Log::info('[UploadCast] Resultado da atualização: ' . ($saveResult ? 'sucesso' : 'falha'));
                        });

                        // Remover o cache
                        Cache::forget($cacheKey);
                        Log::info("[UploadCast] Cache removido: {$cacheKey}");
                    }

                    return $fileHash;
                } else {
                    Log::warning("[UploadCast] Upload falhou, nenhum fileHash retornado para {$modelTable}/{$modelId}");
                }
            } catch (Exception $e) {
                // Em caso de erro, remover o cache
                Cache::forget($cacheKey);
                Log::error("[UploadCast] Erro durante upload para {$modelTable}/{$modelId}: " . $e->getMessage());
                Log::error('[UploadCast] Stack trace: ' . $e->getTraceAsString());
                throw $e;
            }

            Log::info("[UploadCast] Retornando nome temporário: {$tempFileName}");

            return $tempFileName;
        }

        // Liberar cache
        Cache::forget($cacheKey);
        Log::info("[UploadCast] Cache liberado para {$cacheKey} (fluxo padrão)");

        // Se chegar aqui, estamos tratando de um valor original
        $originalValue = is_string($model->getOriginal($key)) ? basename($model->getOriginal($key)) : null;
        Log::info("[UploadCast] Retornando valor original para {$modelTable}/{$modelId}: {$originalValue}");

        return $originalValue;
    }
}
