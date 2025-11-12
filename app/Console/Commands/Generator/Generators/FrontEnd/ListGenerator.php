<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Coduo\PHPHumanizer\StringHumanizer;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ListGenerator
{
    use FrontendPathTrait;

    private TemplateManager $templateManager;

    public function __construct(TemplateManager $templateManager)
    {
        $this->templateManager = $templateManager;
    }

    public function generate(array $config): bool
    {
        $modelName = $config['model'];
        $domain = $config['domain'];
        $schema = $config['schema'];

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
        $fileName = 'index.vue';
        $filePath = $fullPath . '/' . $fileName;

        // Verificar se o arquivo já existe
        if (File::exists($filePath) && !($config['force'] ?? false)) {
            return false;
        }

        // Construir variáveis para o template
        $templateVars = $this->buildTemplateVariables($config);

        // Usar stub antiga
        $listContent = $this->templateManager->processStub(
            'FrontEnd/listar.stub',
            $templateVars
        );

        // Salvar o arquivo
        File::put($filePath, $listContent);

        return true;
    }

    private function buildTemplateVariables(array $config): array
    {
        $modelName = $config['model'];
        $domain = $config['domain'];
        $schema = $config['schema'];

        // Construir store name
        $storeName = 'use' . $modelName . 'Store';

        // Construir interface name
        $interfaceName = "I$modelName";

        // Construir entity singular var
        $entitySingularVar = Str::snake(strtolower(Str::singular($domain)));

        // Construir headers da tabela
        $headers = $this->buildTableHeaders($schema);

        // Construir terms da tabela
        $terms = $this->buildTableTerms($schema, $modelName);

        return [
            '{{list_title}}' => StringHumanizer::humanize(str($domain)->plural()),
            '{{store_name}}' => $storeName,
            '{{interface_name}}' => $interfaceName,
            '{{entity_singular_var}}' => $entitySingularVar,
            '{{header}}' => $headers,
            '{{terms}}' => $terms,
        ];
    }

    private function buildTableHeaders(string $schema): string
    {
        $headers = [];
        $columns = explode(';', rtrim($schema, ';'));

        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);

            if (!$field) {
                continue;
            }

            // Ignorar campos como timestamps, password, etc.
            if (in_array($field, ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'])) {
                continue;
            }

            $headers[] = $this->createStubHeaderItem($field);
        }

        return '[' . implode(',', $headers) . "\n]";
    }

    /**
     * Constrói os terms da tabela para busca
     */
    private function buildTableTerms(string $schema, string $modelName): string
    {
        $terms = [];
        $columns = explode(';', rtrim($schema, ';'));

        foreach ($columns as $column) {
            @[$field, $params] = explode('=', $column);

            if (!$field) {
                continue;
            }

            // Ignorar campos como timestamps, password, etc.
            if (in_array($field, ['created_at', 'updated_at', 'deleted_at', 'password', 'remember_token'])) {
                continue;
            }

            $terms[] = $this->createStubTermsItem($modelName, $field);
        }

        return '[' . implode(',', $terms) . "\n]";
    }

    /**
     * Cria um item de header para a tabela sem i18n
     */
    private function createStubHeaderItem(string $field): string
    {
        return "\n\t{\n\t\ttitle: '" . StringHumanizer::humanize($field) . "',\n\t\tkey: '$field',\n\t\tsortable: true\n\t}";
    }

    /**
     * Cria um item de term para busca
     */
    private function createStubTermsItem(string $crudName, string $field): string
    {
        return "\n\t{\n\t\ttitle: '" . StringHumanizer::humanize($field) . "',\n\t\tvalue: '" . strtolower($field) . "'\n\t}";
    }
}
