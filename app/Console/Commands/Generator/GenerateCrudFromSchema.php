<?php

namespace App\Console\Commands\Generator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

class GenerateCrudFromSchema extends Command
{
    protected $signature = 'generate:from-schema {schema?} {--file=} {--domain=Admin} {--force} {--skip-frontend} {--skip-backend}';
    protected $description = 'Gera CRUDs a partir de um schema simplificado';

    public function handle(): int
    {
        $this->info('🚀 Gerador de CRUD Automático via Schema');

        // Obter o schema
        $schema = $this->getSchema();
        if (empty($schema)) {
            $this->error('Schema não fornecido!');
            return CommandAlias::FAILURE;
        }

        // Processar o schema
        $tables = $this->parseSchema($schema);
        if (empty($tables)) {
            $this->error('Nenhuma tabela definida no schema!');
            return CommandAlias::FAILURE;
        }

        // Gerar CRUD para cada tabela
        $domain = $this->option('domain');
        foreach ($tables as $tableName => $fields) {
            $this->info("Gerando CRUD para: $tableName");

            // Criar configuração para o gerador
            $config = $this->buildCrudConfig($tableName, $fields, $domain);

            // Gerar CRUD usando o comando existente
            $this->generateCrud($config);

            $this->info("✅ CRUD para '$tableName' gerado com sucesso!");
            $this->newLine();
        }

        return CommandAlias::SUCCESS;
    }

    /**
     * Obtém o schema de entrada, seja via argumento, arquivo ou prompt
     */
    private function getSchema(): string
    {
        // Verificar se foi fornecido um arquivo
        if ($this->option('file')) {
            $filePath = $this->option('file');
            if (File::exists($filePath)) {
                return File::get($filePath);
            } else {
                $this->error("Arquivo não encontrado: $filePath");
                return '';
            }
        }

        // Verificar se foi fornecido como argumento
        if ($this->argument('schema')) {
            return $this->argument('schema');
        }

        // Solicitar via prompt
        return $this->ask('Digite o schema no formato "tabela: campo=tipo,tamanho,req;campo2=tipo2,..."');
    }

    /**
     * Analisa o schema fornecido e retorna um array de tabelas e seus campos
     */
    private function parseSchema(string $schema): array
    {
        $tables = [];
        $lines = preg_split('/\r\n|\r|\n/', $schema);

        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line)) {
                continue;
            }

            // Formato esperado: tabela: campo=tipo,tamanho,req;campo2=tipo2,...
            if (preg_match('/^([a-z0-9_]+):\s*(.*?)$/i', $line, $matches)) {
                $tableName = $matches[1];
                $fieldsString = $matches[2];

                $fields = $this->parseFields($fieldsString);
                $tables[$tableName] = $fields;
            }
        }

        return $tables;
    }

    /**
     * Analisa a string de campos e retorna um array com as definições
     */
    private function parseFields(string $fieldsString): array
    {
        $fields = [];
        $fieldsList = explode(';', $fieldsString);

        foreach ($fieldsList as $fieldDef) {
            $fieldDef = trim($fieldDef);
            if (empty($fieldDef)) {
                continue;
            }

            // Formato: campo=tipo,tamanho,req
            if (preg_match('/^([a-z0-9_]+)=([a-z0-9_]+)(,.*)?$/i', $fieldDef, $matches)) {
                $fieldName = $matches[1];
                $fieldType = $matches[2];
                $params = isset($matches[3]) ? $matches[3] : '';

                // Extrair parâmetros
                $options = [];
                if ($params) {
                    $paramsList = explode(',', substr($params, 1));
                    foreach ($paramsList as $param) {
                        $param = trim($param);
                        if ($param === 'req') {
                            $options['required'] = true;
                        } elseif (is_numeric($param)) {
                            $options['size'] = $param;
                        } else {
                            $options['extra'] = $param;
                        }
                    }
                }

                $fields[$fieldName] = [
                    'type' => $fieldType,
                    'options' => $options
                ];
            }
        }

        return $fields;
    }

    /**
     * Constrói a configuração para o gerador de CRUD
     */
    private function buildCrudConfig(string $tableName, array $fields, string $domain): array
    {
        // Determinar o nome do modelo (singular e PascalCase)
        $modelName = Str::studly(Str::singular($tableName));

        // Construir a string de schema para o gerador
        $schema = $this->buildSchemaString($fields);

        // Identificar chaves estrangeiras
        $foreignKeys = $this->identifyForeignKeys($fields, $tableName);

        return [
            'domain' => $domain,
            'name' => $modelName,
            'model' => $modelName,
            'migration' => 'create_' . $tableName . '_table',
            'service' => $modelName . 'Service',
            'schema' => $schema,
            'foreignKeys' => $foreignKeys,
            'generateTests' => false,
            'generateDocs' => false,
            'force' => $this->option('force'),
            'skip-frontend' => $this->option('skip-frontend'),
            'skip-backend' => $this->option('skip-backend'),
        ];
    }

    /**
     * Constrói a string de schema para o gerador de CRUD
     */
    private function buildSchemaString(array $fields): string
    {
        $schemaFields = [];

        foreach ($fields as $fieldName => $fieldInfo) {
            // Ignorar campos especiais
            if (in_array($fieldName, ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            // Ignorar chaves estrangeiras (serão tratadas separadamente)
            if (Str::endsWith($fieldName, '_id')) {
                continue;
            }

            $type = $fieldInfo['type'];
            $options = $fieldInfo['options'];

            $fieldSchema = $fieldName . '=' . $type;

            if (isset($options['size'])) {
                $fieldSchema .= ',' . $options['size'];
            }

            if (isset($options['required']) && $options['required']) {
                $fieldSchema .= ',req';
            }

            $schemaFields[] = $fieldSchema;
        }

        return implode(';', $schemaFields);
    }

    /**
     * Identifica chaves estrangeiras com base nos campos que terminam com _id
     */
    private function identifyForeignKeys(array $fields, string $currentTable): array
    {
        $foreignKeys = [];

        foreach ($fields as $fieldName => $fieldInfo) {
            // Verificar se é uma possível chave estrangeira
            if (Str::endsWith($fieldName, '_id') && $fieldName !== 'id') {
                $relatedTable = Str::beforeLast($fieldName, '_id');
                $relatedModel = Str::studly(Str::singular($relatedTable));

                // Adicionar à lista de chaves estrangeiras
                $foreignKeys[] = [
                    'domain' => $this->option('domain'),
                    'model' => $relatedModel,
                    'relation' => 'belongsTo',
                    'required' => isset($fieldInfo['options']['required']) && $fieldInfo['options']['required']
                ];

                // Também é possível adicionar hasMany na relação inversa
                // (isso seria feito quando o modelo relacionado for gerado)
            }
        }

        return $foreignKeys;
    }

    /**
     * Executa a geração do CRUD usando o gerador existente
     */
    private function generateCrud(array $config): void
    {
        $params = [];

        // Converter a configuração em parâmetros para o command bus
        $params['--force'] = $config['force'] ?? false;
        $params['--skip-frontend'] = $config['skip-frontend'] ?? false;
        $params['--skip-backend'] = $config['skip-backend'] ?? false;
        $params['--config'] = json_encode($config);

        Artisan::call('generate:crud', $params);

        // Exibir output do comando
        $this->line(Artisan::output());
    }
}
