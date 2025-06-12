<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class FormGenerator
{
    use FrontendPathTrait;

    private TemplateManager $templateManager;
    private FieldsGenerator $fieldsGenerator;

    public function __construct(TemplateManager $templateManager, FieldsGenerator $fieldsGenerator)
    {
        $this->templateManager = $templateManager;
        $this->fieldsGenerator = $fieldsGenerator;
    }

    public function generate(array $config): bool
    {
        $modelName = $config['model'];
        $domain = $config['domain'];

        $frontEndAbsoluteDir = $this->getFrontendPath();

        $fullPath = sprintf(
            '%s/%s/%s',
            $frontEndAbsoluteDir,
            'pages',
            Str::snake($domain, '-')
        );

        // Criar diretório se não existir
        if (!File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        // Nome do arquivo seguindo padrão antigo
        $fileName = "{$modelName}Form.vue";
        $filePath = $fullPath . '/' . $fileName;

        // Verificar se o arquivo já existe
        if (File::exists($filePath) && !($config['force'] ?? false)) {
            return false;
        }

        // Construir variáveis para o template
        $templateVars = $this->buildTemplateVariables($config);

        // Usar stub antiga
        $formContent = $this->templateManager->processStub(
            'FrontEnd/form.stub',
            $templateVars
        );

        // Salvar o arquivo
        File::put($filePath, $formContent);

        return true;
    }

    private function buildTemplateVariables(array $config): array
    {
        $modelName = $config['model'];
        $domain = $config['domain'];
        $foreignKeys = $config['foreignKeys'] ?? [];
        $schema = $config['schema'] ?? [];

        // Converter schema de string para array se necessário
        if (is_string($schema)) {
            $schema = $this->parseSchemaString($schema);
        }

        // Construir imports para FK
        $imports = $this->buildImports($foreignKeys);

        // Construir store name
        $storeName = 'use' . $modelName . 'Store';

        // Construir interface name
        $interfaceName = $modelName . 'Interface';

        // Construir métodos fetchs
        $methodsFetchs = $this->buildFetchMethods($foreignKeys);

        // Construir refs de FK
        $fkRefsState = $this->buildForeignKeyRefs($foreignKeys);

        // Construir métodos FK
        $fkMethods = $this->buildForeignKeyMethods($foreignKeys);

        // Construir título do form
        $formTitle = "isEditing ? 'Editar " . $modelName . "' : 'Novo " . $modelName . "'";

        // Gerar campos do formulário usando FieldsGenerator
        $formFields = $this->fieldsGenerator->generateFormFields($schema);

        return [
            '{{imports}}' => implode("\n", $imports),
            '{{store_name}}' => $storeName,
            '{{interface_name}}' => $interfaceName,
            '{{methods_fetchs}}' => implode("\n", $methodsFetchs),
            '{{fk_refs_state}}' => implode(",\n  ", $fkRefsState),
            '{{fk_methods}}' => implode(",\n  ", $fkMethods),
            '{{form_title}}' => $formTitle,
            '{{fields}}' => $formFields,
            '{{fk_inputs}}' => '', // TODO: Implementar inputs específicos para FK
            '{{entity_singular_var}}' => Str::kebab($domain),
        ];
    }

    private function buildImports(array $foreignKeys): array
    {
        $imports = [];
        foreach ($foreignKeys as $fk) {
            $interfaceName = $fk['model'] . 'Interface';
            $domainKebab = Str::kebab($fk['domain']);
            $imports[] = "import type { {$interfaceName} } from '@/pages/{$domainKebab}/types'";
        }
        return $imports;
    }

    private function buildFetchMethods(array $foreignKeys): array
    {
        $methods = [];
        foreach ($foreignKeys as $fk) {
            $methodName = 'fetch' . Str::plural($fk['model']);
            $methods[] = "  {$methodName}()";
        }
        return $methods;
    }

    private function buildForeignKeyRefs(array $foreignKeys): array
    {
        $refs = [];
        foreach ($foreignKeys as $fk) {
            $pluralName = Str::plural(strtolower($fk['model']));
            $refs[] = "{$pluralName}";
        }
        return $refs;
    }

    private function buildForeignKeyMethods(array $foreignKeys): array
    {
        $methods = [];
        foreach ($foreignKeys as $fk) {
            $methodName = 'fetch' . Str::plural($fk['model']);
            $methods[] = "{$methodName}";
        }
        return $methods;
    }

    /**
     * Converte schema de string para array
     */
    private function parseSchemaString(string $schema): array
    {
        $fields = [];
        $columns = explode(';', rtrim($schema, ';'));

        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);
            @[$type, $option1, $option2, $required] = explode(',', $params ?? '');

            if (!$field) {
                continue;
            }

            $fieldData = [
                'name' => $field,
                'type' => $type ?? 'string',
                'option1' => $option1 ?? null,
                'option2' => $option2 ?? null,
                'required' => $required === 'req',
                'label' => Str::title(str_replace('_', ' ', $field)),
            ];

            // Adicionar informações de tamanho para campos string e text
            if (in_array(strtolower($type), ['string', 'text']) && $option1 && is_numeric($option1)) {
                $fieldData['max_length'] = intval($option1);
            }

            $fields[] = $fieldData;
        }

        return $fields;
    }
}
