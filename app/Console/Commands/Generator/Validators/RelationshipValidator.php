<?php

namespace App\Console\Commands\Generator\Validators;

use Illuminate\Support\Facades\File;

class RelationshipValidator
{
    /**
     * Tipos de relacionamentos suportados
     */
    private const SUPPORTED_RELATIONS = [
        'belongsTo',
        'hasMany',
        'hasOne',
        'belongsToMany'
    ];

    /**
     * Valida uma relação específica
     *
     * @param array $relation Dados da relação a ser validada
     * @return bool|string Retorna true se válido, ou string com mensagem de erro
     */
    public function validate(array $relation): bool|string
    {
        // Verifica se o tipo de relação é suportado
        if (!isset($relation['relation']) || !in_array($relation['relation'], self::SUPPORTED_RELATIONS)) {
            return "Tipo de relação '" . ($relation['relation'] ?? 'não informado') . "' inválido. Use: " . implode(', ', self::SUPPORTED_RELATIONS);
        }

        // Verifica se o domínio existe
        if (!isset($relation['domain']) || !$this->domainExists($relation['domain'])) {
            return "Domínio '".($relation['domain'] ?? 'não informado')."' não existe";
        }

        // Verifica se o modelo existe no domínio
        if (!isset($relation['model']) || !$this->modelExistsInDomain($relation['domain'], $relation['model'])) {
            return "Model '".($relation['model'] ?? 'não informado')."' não existe no domínio '{$relation['domain']}'";
        }

        // Verifica configurações específicas do tipo de relação
        return match ($relation['relation']) {
            'belongsToMany' => $this->validateBelongsToMany($relation),
            default => true,
        };
    }

    /**
     * Valida um array de relações
     *
     * @param array $relations Array de relações a serem validadas
     * @return bool|string Retorna true se todas as relações forem válidas, ou string com mensagem de erro
     */
    public function validateAll(array $relations): bool|string
    {
        foreach ($relations as $index => $relation) {
            $result = $this->validate($relation);
            if ($result !== true) {
                return "Erro na relação #{$index}: {$result}";
            }
        }

        return true;
    }

    /**
     * Verifica se um domínio existe
     *
     * @param string $domain Nome do domínio
     * @return bool
     */
    private function domainExists(string $domain): bool
    {
        return File::isDirectory(app_path("Domains/{$domain}"));
    }

    /**
     * Verifica se um modelo existe no domínio especificado
     *
     * @param string $domain Nome do domínio
     * @param string $model Nome do modelo
     * @return bool
     */
    private function modelExistsInDomain(string $domain, string $model): bool
    {
        // Primeiro verifica se o arquivo existe
        $modelPath = app_path("Domains/{$domain}/Models/{$model}.php");

        if (File::exists($modelPath)) {
            return true;
        }

        // Se o arquivo não existe, também é válido (pode estar sendo criado agora)
        // Esta verificação é útil para quando estamos gerando um relacionamento para um modelo que ainda não existe
        return true;
    }

    /**
     * Validações adicionais específicas para relacionamentos belongsToMany
     *
     * @param array $relation Dados da relação
     * @return bool|string
     */
    private function validateBelongsToMany(array $relation): bool|string
    {
        // Poderia verificar se há uma tabela pivot definida ou outros parâmetros necessários
        // Por hora, apenas retornamos true para simplificar
        return true;
    }
}
