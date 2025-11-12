<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Str;

class FieldsGenerator
{
    private array $fieldTypeMapping = [
        'string' => 'input',
        'text' => 'textarea',
        'integer' => 'input',
        'bigInteger' => 'input',
        'decimal' => 'currency',
        'float' => 'currency',
        'double' => 'currency',
        'boolean' => 'switch',
        'date' => 'date',
        'datetime' => 'date',
        'timestamp' => 'date',
        'time' => 'input',
        'json' => 'textarea',
        // Campos específicos baseados no nome
        'cpf' => 'cpf',
        'cnpj' => 'cnpj',
        'telefone' => 'telefone',
        'celular' => 'celular',
        'currency' => 'currency',
        'image' => 'imageinput',
        'file' => 'fileinput',
        'checkbox' => 'checkbox',
        'enum' => 'select',
    ];

    public function __construct(
        private readonly TemplateManager $templateManager
    ) {}

    /**
     * Gera o campo baseado no tipo e configuração
     */
    public function generateField(array $fieldConfig): string
    {
        $fieldType = $this->determineFieldType($fieldConfig);
        $stubPath = "FrontEnd/Fields/{$fieldType}.frontend.stub";

        // Verificar se stub existe, senão usar input como fallback
        $fullStubPath = app_path('Domains/Shared/Stubs/' . $stubPath);
        if (!file_exists($fullStubPath)) {
            $fieldType = 'input'; // fallback para input básico
            $stubPath = "FrontEnd/Fields/{$fieldType}.frontend.stub";
        }

        $variables = $this->buildFieldVariables($fieldConfig);

        return $this->generateColField($this->templateManager->processStub($stubPath, $variables));
    }

    public function generateColField(string $field): string
    {
        return "<VCol cols=\"12\" md=\"6\" lg=\"4\" xl=\"3\">$field</VCol>";
    }

    /**
     * Determina o tipo de campo baseado na configuração
     */
    private function determineFieldType(array $fieldConfig): string
    {
        $fieldName = strtolower($fieldConfig['name']);
        $dataType = $fieldConfig['type'] ?? 'string';

        // Verificar campos específicos baseados no nome
        if (str_contains($fieldName, 'cpf')) {
            return 'cpf';
        }
        if (str_contains($fieldName, 'cnpj')) {
            return 'cnpj';
        }
        if (str_contains($fieldName, 'telefone')) {
            return 'telefone';
        }
        if (str_contains($fieldName, 'celular') || str_contains($fieldName, 'mobile')) {
            return 'celular';
        }
        if (str_contains($fieldName, 'preco') || str_contains($fieldName, 'valor') || str_contains($fieldName, 'price')) {
            return 'currency';
        }
        if (str_contains($fieldName, 'image') || str_contains($fieldName, 'foto') || str_contains($fieldName, 'avatar')) {
            return 'imageinput';
        }
        if (str_contains($fieldName, 'file') || str_contains($fieldName, 'arquivo') || str_contains($fieldName, 'document')) {
            return 'fileinput';
        }

        // Verificar se é foreign key (autocomplete)
        if (isset($fieldConfig['foreign_key']) && $fieldConfig['foreign_key']) {
            return 'autocomplete';
        }

        // Mapear por tipo de dados
        return $this->fieldTypeMapping[$dataType] ?? 'input';
    }

    /**
     * Constrói as variáveis para o template do campo
     */
    private function buildFieldVariables(array $fieldConfig): array
    {
        $fieldName = $fieldConfig['name'];
        $label = $fieldConfig['label'] ?? Str::title(str_replace('_', ' ', $fieldName));
        $placeholder = $fieldConfig['placeholder'] ?? "Digite {$label}";

        // Construir regras de validação
        $rules = $this->buildValidationRules($fieldConfig);

        $variables = [
            '{{vModel}}' => "data.{$fieldName}",
            '{{label}}' => "'{$label}'",
            '{{placeholder}}' => "'{$placeholder}'",
            '{{rules}}' => $rules,
        ];

        // Adicionar atributo maxlength para campos string e text
        if ((isset($fieldConfig['type']) && in_array(strtolower($fieldConfig['type']), ['string', 'text'])) && isset($fieldConfig['max_length'])) {
            $variables['{{maxlength}}'] = ' maxlength="' . $fieldConfig['max_length'] . '"';
        } else {
            $variables['{{maxlength}}'] = '';
        }

        // Adicionar tipo de campo para campos date/datetime
        if (isset($fieldConfig['type']) && in_array(strtolower($fieldConfig['type']), ['date', 'datetime'])) {
            $variables['{{fieldType}}'] = strtolower($fieldConfig['type']) === 'datetime' ? 'datetime-local' : 'date';
        } else {
            $variables['{{fieldType}}'] = 'text'; // Valor padrão para outros tipos
        }

        // Adicionar items para campos enum (select)
        if (isset($fieldConfig['type']) && strtolower($fieldConfig['type']) === 'enum' && isset($fieldConfig['enum_values'])) {
            $enumItems = array_map(function ($value) {
                return "{ title: '{$value}', value: '{$value}' }";
            }, $fieldConfig['enum_values']);
            $variables['{{items}}'] = '[' . implode(', ', $enumItems) . ']';
        } else {
            $variables['{{items}}'] = '[]';
        }

        return $variables;
    }

    /**
     * Constrói as regras de validação para o campo
     */
    private function buildValidationRules(array $fieldConfig): string
    {
        $rules = [];

        // Regra obrigatória
        if (isset($fieldConfig['required']) && $fieldConfig['required']) {
            $rules[] = 'rules.requiredValidator';
        }

        // Regras específicas por tipo
        $fieldType = $this->determineFieldType($fieldConfig);

        switch ($fieldType) {
            case 'cpf':
                $rules[] = 'rules.cpfValidator';
                break;
            case 'cnpj':
                $rules[] = 'rules.cnpjValidator';
                break;
            case 'telefone':
            case 'celular':
                $rules[] = 'rules.telefoneValidator';
                break;
        }

        // Adicionar validação de tamanho para campos string e text
        if (isset($fieldConfig['type']) && in_array(strtolower($fieldConfig['type']), ['string', 'text']) && isset($fieldConfig['max_length'])) {
            $rules[] = "rules.maxLengthValidator(data.{$fieldConfig['name']},{$fieldConfig['max_length']})";
        }

        // Adicionar validação de tamanho mínimo se especificado
        if (isset($fieldConfig['min_length'])) {
            $rules[] = "rules.maxLengthValidator(data.{$fieldConfig['name']},{$fieldConfig['min_length']})";
        }

        return implode(', ', $rules);
    }

    /**
     * Gera todos os campos para um formulário
     */
    public function generateFormFields(array $schema): string
    {
        $fields = [];

        foreach ($schema as $fieldConfig) {
            // Pular campos que não devem aparecer no formulário
            if (in_array($fieldConfig['name'], ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $field = $this->generateField($fieldConfig);
            $fields[] = $field;
        }

        return implode("\n            ", $fields);
    }
}
