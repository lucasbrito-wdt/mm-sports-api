<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Exception;

class FormGenerator
{
    use FrontendPathTrait;

    private TemplateManager $templateManager;

    private FieldsGenerator $fieldsGenerator;

    private bool $oneToManyRelationship = false;

    public function __construct(TemplateManager $templateManager, FieldsGenerator $fieldsGenerator)
    {
        $this->templateManager = $templateManager;
        $this->fieldsGenerator = $fieldsGenerator;
    }

    public function generate(array $config): bool
    {
        $modelName = $config['model'];
        $domain = $config['domain'];
        $shouldAttach = $config['shouldAttach'] ?? false;

        // Se deve anexar, procurar formulário existente
        if ($shouldAttach) {
            return $this->attachToExistingForm($config);
        }

        // Caso contrário, criar novo formulário
        return $this->createNewForm($config);
    }

    /**
     * Anexa campos a um formulário existente do domínio
     */
    private function attachToExistingForm(array $config): bool
    {
        $domain = $config['domain'];
        $frontEndAbsoluteDir = $this->getFrontendPath();

        $domainPath = sprintf(
            '%s/%s/%s/%s',
            $frontEndAbsoluteDir,
            'pages',
            Str::snake($domain, '-'),
            'components'
        );

        if (!File::exists($domainPath)) {
            throw new Exception("Diretório do domínio não encontrado: $domainPath");
        }

        // Procurar por arquivos *Form.vue existentes
        $formFiles = glob($domainPath . '/*Form.vue');

        if (empty($formFiles)) {
            throw new Exception("Nenhum formulário encontrado no domínio $domain para anexar");
        }

        // Usar o primeiro formulário encontrado (principal do domínio)
        $existingFormPath = $formFiles[0];

        // Aplicar formAttach ao arquivo existente
        $this->processFormAttachToExistingFile($config, $existingFormPath);

        return true;
    }

    /**
     * Cria um novo formulário
     */
    private function createNewForm(array $config): bool
    {
        $modelName = $config['model'];
        $domain = $config['domain'];

        $frontEndAbsoluteDir = $this->getFrontendPath();

        $fullPath = sprintf(
            '%s/%s/%s/%s',
            $frontEndAbsoluteDir,
            'pages',
            Str::snake($domain, '-'),
            'components'
        );

        // Criar diretório se não existir
        if (! File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        // Nome do arquivo seguindo padrão antigo
        $fileName = "{$modelName}Form.vue";
        $filePath = $fullPath . '/' . $fileName;

        // Verificar se o arquivo já existe
        if (File::exists($filePath) && ! ($config['force'] ?? false)) {
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
        $interfaceName = 'I' . $modelName;

        // Construir métodos fetchs
        $methodsFetchs = $this->buildFetchMethods($foreignKeys);

        // Construir refs de FK
        $fkRefsState = $this->buildForeignKeyRefs($foreignKeys);

        // Construir métodos FK
        $fkMethods = $this->buildForeignKeyMethods($foreignKeys);

        // Construir inputs de FK
        $fkInputs = $this->buildFkInputs($foreignKeys);

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
            '{{fk_inputs}}' => implode(",\n  ", $fkInputs),
            '{{entity_singular_var}}' => Str::kebab($domain),
        ];
    }

    private function buildImports(array $foreignKeys): array
    {
        $imports = [];
        foreach ($foreignKeys as $fk) {
            $interfaceName = 'I' . $fk['model'];
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
        if (! empty($refs)) {
            $refs[] = 'loading';
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
            @[$field, $params] = explode('=', $column, 2);

            if (!$field || !$params) {
                continue;
            }

            $paramParts = explode(',', $params);
            $type = $paramParts[0] ?? 'string';

            $required = false;
            $option1 = null;
            $option2 = null;
            $enumValues = [];

            // Processar parâmetros
            for ($i = 1; $i < count($paramParts); $i++) {
                $part = $paramParts[$i];

                // Verificar se é 'req'
                if (strtolower(trim($part)) === 'req') {
                    $required = true;
                    continue;
                }

                // Verificar se contém valores de enum (com |)
                if (str_contains($part, '|')) {
                    $enumValues = array_map('trim', explode('|', $part));
                    continue;
                }

                // Caso contrário, é uma opção
                if (!$option1) {
                    $option1 = $part;
                } elseif (!$option2) {
                    $option2 = $part;
                }
            }

            $fieldData = [
                'name' => $field,
                'type' => $type,
                'option1' => $option1,
                'option2' => $option2,
                'required' => $required,
                'label' => Str::title(str_replace('_', ' ', $field)),
            ];

            // Adicionar informações de tamanho para campos string e text
            if (in_array(strtolower($type), ['string', 'text']) && $option1 && is_numeric($option1)) {
                $fieldData['max_length'] = intval($option1);
            }

            // Adicionar valores de enum se existirem
            if (!empty($enumValues)) {
                $fieldData['enum_values'] = $enumValues;
            }

            $fields[] = $fieldData;
        }

        return $fields;
    }

    public function buildFkInputs(array $foreignKeys): array
    {
        $inputs = [];
        foreach ($foreignKeys as $fk) {
            $dataName = Str::lower("{$fk['domain']}");
            $itemsName = Str::plural(Str::lower("{$fk['domain']}"));
            $loading = Str::plural(Str::lower("{$fk['domain']}"));
            $rules = $fk['required'] ?? false ? '[rules.requiredValidator]' : '[]';

            $inputs[] = <<<EOT
            <VCol cols="12" md="6" lg="4" xl="3">
                <AppAutocomplete
                    v-model="data.{$dataName}_id"
                    :items="$itemsName"
                    label="{$fk['domain']}"
                    :return-object="false"
                    :loading="loading.$loading"
                    :rules="$rules"
                    item-value="id"
                    item-title="[Informe Nome do Campo]"
                />
            </VCol>
            EOT;
        }

        return $inputs;
    }

    /**
     * @throws Exception
     */
    private function formAttach($filePath, $domainName, $crudName, $fields, $attributesDefault, $interface, $title)
    {
        $domainNamespace = sprintf('App\\Domains\\%s\\Models\\%s', $domainName, $crudName);

        // Verificar se a classe existe antes de instanciar
        if (!class_exists($domainNamespace)) {
            // Se a classe não existe, usar um nome padrão para a FK
            $fkName = strtolower($crudName);
        } else {
            $modelInstance = new $domainNamespace();
            $fkName = str($modelInstance->getTable())->lower();
        }

        $fields = array_map(fn($field) => str_replace('data.', 'item.', $field), $fields);
        $newContent = implode("\n\t\t\t\t\t\t", $fields);

        $import = sprintf("import type { %s } from '@/pages/%s/types'", $interface, strtolower($domainName));

        if ($this->oneToManyRelationship) {
            $input = $this->generateOneToManyTemplate($fkName, $title, $newContent, $interface, $attributesDefault);
        } else {
            $input = $this->generateDefaultTemplate($title, $newContent);
        }

        if (!File::exists($filePath)) {
            throw new Exception("Arquivo não encontrado: $filePath");
        }

        $content = File::get($filePath);

        // Nova lógica de substituição mais precisa
        if (preg_match('/<template>\s*<LayoutForms[\s\S]*?<\/template>\s*<\/LayoutForms>/m', $content, $matches)) {
            $templateContent = $matches[0];
            $lastClosingTag = strrpos($templateContent, '</template>');

            if ($lastClosingTag !== false) {
                $beforeTemplate = substr($content, 0, strpos($content, $templateContent) + $lastClosingTag);
                $afterTemplate = substr($content, strpos($content, $templateContent) + $lastClosingTag);

                $content = $beforeTemplate . "\n" . $input . $afterTemplate;
            }
        }

        // Adiciona import apenas se não existir
        if (!str_contains($content, $import)) {
            $scriptSetupPos = strpos($content, '<script setup lang="ts">') + strlen('<script setup lang="ts">');
            $content = substr_replace($content, "\n" . $import, $scriptSetupPos, 0);
        }

        File::put($filePath, $content);
    }

    /**
     * Gera template para relacionamento OneToMany
     */
    private function generateOneToManyTemplate($fkName, $title, $newContent, $interface, $attributesDefault): string
    {
        return <<<EOT
            <CDFManager
                v-model:items="data.{$fkName}"
                v-model:form="form"
                title="{$title}"
                item-label="{$title}"
                message-add="Adicionar {$title}"
                :template="{$attributesDefault}"
            >
                <template #content="{ item, index }: { item: {$interface}, index: number }">
                    {$newContent}
                </template>
            </CDFManager>
        EOT;
    }

    /**
     * Gera template padrão
     */
    private function generateDefaultTemplate($title, $newContent): string
    {
        return <<<EOT
            {$newContent}
        EOT;
    }

    /**
     * Define se o relacionamento é OneToMany
     */
    public function setOneToManyRelationship(bool $oneToMany): void
    {
        $this->oneToManyRelationship = $oneToMany;
    }

    /**
     * Método público para aplicar formAttach em arquivos existentes
     */
    public function applyFormAttach(string $filePath, string $domainName, string $crudName, array $fields, array $attributesDefault, string $interface, string $title, bool $oneToMany = false): bool
    {
        try {
            $this->setOneToManyRelationship($oneToMany);
            $this->formAttach($filePath, $domainName, $crudName, $fields, $attributesDefault, $interface, $title);
            return true;
        } catch (Exception $e) {
            error_log("Erro ao aplicar formAttach: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Processa o formAttach para um arquivo existente
     */
    private function processFormAttachToExistingFile(array $config, string $filePath): void
    {
        $foreignKeys = $config['foreignKeys'] ?? [];

        // Verificar se há relacionamentos OneToMany
        $hasOneToMany = false;
        foreach ($foreignKeys as $fk) {
            if (($fk['relation'] ?? '') === 'hasMany') {
                $hasOneToMany = true;
                break;
            }
        }

        // Definir o tipo de template baseado no relacionamento
        $this->setOneToManyRelationship($hasOneToMany);

        // Gerar campos para attach
        $fields = $this->buildAttachFields($config);
        $attributesDefault = $this->buildDefaultAttributesForAttach($config);
        $interface = 'I' . $config['model'];
        $title = $config['model'];

        try {
            $this->formAttach(
                $filePath,
                $config['domain'],
                $config['model'],
                $fields,
                $attributesDefault,
                $interface,
                $title
            );
        } catch (Exception $e) {
            // Log erro mas não interrompe o processo
            error_log("FormGenerator: Erro ao processar formAttach: " . $e->getMessage());
            throw $e; // Re-lança para anexo, pois é crítico
        }
    }

    /**
     * Processa o formAttach para formulários gerados
     */
    private function processFormAttachIfNeeded(array $config, string $filePath): void
    {
        // Verificar se deve anexar ao formulário (configuração do usuário)
        $shouldAttach = $config['shouldAttach'] ?? false;

        if (!$shouldAttach) {
            return; // Não aplicar formAttach se o usuário não escolheu anexar
        }

        $foreignKeys = $config['foreignKeys'] ?? [];

        // Verificar se há relacionamentos OneToMany
        $hasOneToMany = false;

        foreach ($foreignKeys as $fk) {
            if (($fk['relation'] ?? '') === 'hasMany') {
                $hasOneToMany = true;
                break;
            }
        }

        // Definir o tipo de template baseado no relacionamento
        $this->setOneToManyRelationship($hasOneToMany);

        // Gerar campos para attach
        $fields = $this->buildAttachFields($config);
        $attributesDefault = $this->buildDefaultAttributesForAttach($config);
        $interface = 'I' . $config['model'];
        $title = $config['model'];

        try {
            $this->formAttach(
                $filePath,
                $config['domain'],
                $config['model'],
                $fields,
                $attributesDefault,
                $interface,
                $title
            );
        } catch (Exception $e) {
            // Log erro mas não interrompe o processo
            error_log("FormGenerator: Erro ao processar formAttach: " . $e->getMessage());
        }
    }

    /**
     * Constrói os campos para attach
     */
    private function buildAttachFields(array $config): array
    {
        $fields = [];
        $schema = $config['schema'] ?? '';

        if (empty($schema)) {
            // Se não há schema, gerar alguns campos padrão
            $fields[] = '<VCol cols="12" md="6" lg="4" xl="3">
                    <AppTextField
                        v-model="item.nome"
                        label="Nome"
                        placeholder="Digite o nome"
                    />
                </VCol>';
            return $fields;
        }

        $columns = explode(';', rtrim($schema, ';'));

        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);

            if (!$field || in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            // Converter para formato de template
            $fields[] = sprintf(
                '<VCol cols="12" md="6" lg="4" xl="3">
                    <AppTextField
                        v-model="item.%s"
                        label="%s"
                        placeholder="Digite %s"
                    />
                </VCol>',
                $field,
                ucfirst(str_replace('_', ' ', $field)),
                strtolower(str_replace('_', ' ', $field))
            );
        }

        return $fields;
    }

    /**
     * Constrói atributos padrão para attach
     */
    private function buildDefaultAttributesForAttach(array $config): array
    {
        $attributes = [];
        $schema = $config['schema'] ?? '';

        if (empty($schema)) {
            return $attributes;
        }

        $columns = explode(';', rtrim($schema, ';'));

        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);
            @[$type] = explode(',', $params ?? '');

            if (!$field || in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            $defaultValue = match (strtolower($type ?? 'string')) {
                'integer', 'biginteger', 'decimal', 'float' => 'null',
                'boolean' => 'false',
                'date', 'datetime', 'timestamp' => 'null',
                default => "''"
            };

            $attributes[$field] = $defaultValue;
        }

        return $attributes;
    }
}
