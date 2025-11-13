<?php

namespace App\Console\Commands\Generator;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

class GenerateDomainsFromDbml extends Command
{
    protected $signature = 'generate:domains-from-dbml 
                            {file : Caminho para o arquivo DBML}
                            {--domain-prefix= : Prefixo para os nomes dos domínios (opcional)}
                            {--force : Força a criação mesmo se o domínio já existir}
                            {--skip-frontend : Não gera arquivos frontend}
                            {--skip-backend : Não gera arquivos backend}
                            {--dry-run : Apenas mostra a ordem sem executar}';

    protected $description = 'Analisa um arquivo DBML e gera domínios na ordem correta respeitando dependências de chaves estrangeiras';

    private array $tables = [];
    private array $dependencies = [];
    private array $orderedTables = [];

    public function handle(): int
    {
        $this->info('🚀 Gerador de Domínios a partir de DBML');
        $this->newLine();

        $filePath = $this->argument('file');
        
        if (!File::exists($filePath)) {
            $this->error("❌ Arquivo não encontrado: {$filePath}");
            return CommandAlias::FAILURE;
        }

        $dbmlContent = File::get($filePath);
        
        // Parse do DBML
        $this->info('📖 Analisando arquivo DBML...');
        $this->parseDbml($dbmlContent);
        
        if (empty($this->tables)) {
            $this->error('❌ Nenhuma tabela encontrada no arquivo DBML!');
            return CommandAlias::FAILURE;
        }

        // Ordenar tabelas por dependências
        $this->info('🔀 Ordenando tabelas por dependências...');
        $this->orderTablesByDependencies();

        // Exibir ordem
        $this->displayOrder();

        if ($this->option('dry-run')) {
            $this->info('✅ Modo dry-run: nenhuma alteração foi feita.');
            return CommandAlias::SUCCESS;
        }

        // Confirmar execução
        if (!$this->confirm('Deseja prosseguir com a criação dos domínios?', true)) {
            $this->info('Operação cancelada.');
            return CommandAlias::SUCCESS;
        }

        // Gerar domínios na ordem correta
        $this->generateDomains();

        $this->newLine();
        $this->info('✅ Processo concluído!');
        
        return CommandAlias::SUCCESS;
    }

    /**
     * Faz o parse do arquivo DBML e extrai tabelas e dependências
     */
    private function parseDbml(string $content): void
    {
        $lines = preg_split('/\r\n|\r|\n/', $content);
        $currentTable = null;
        $currentFields = [];

        foreach ($lines as $line) {
            $line = trim($line);
            
            // Ignorar linhas vazias e comentários
            if (empty($line) || str_starts_with($line, '//')) {
                continue;
            }

            // Detectar início de tabela
            if (preg_match('/^Table\s+(\w+)\s*\{/', $line, $matches)) {
                // Salvar tabela anterior se existir
                if ($currentTable !== null) {
                    $this->tables[$currentTable] = $currentFields;
                }
                
                $currentTable = $matches[1];
                $currentFields = [];
                continue;
            }

            // Detectar fim de tabela
            if ($line === '}') {
                if ($currentTable !== null) {
                    $this->tables[$currentTable] = $currentFields;
                    $currentTable = null;
                    $currentFields = [];
                }
                continue;
            }

            // Processar campos da tabela
            if ($currentTable !== null) {
                $this->processField($line, $currentTable, $currentFields);
            }
        }

        // Salvar última tabela se existir
        if ($currentTable !== null) {
            $this->tables[$currentTable] = $currentFields;
        }
    }

    /**
     * Processa um campo e identifica chaves estrangeiras
     */
    private function processField(string $line, string $table, array &$fields): void
    {
        // Remover comentários inline
        $line = preg_replace('/\/\/.*$/', '', $line);
        $line = trim($line);

        if (empty($line)) {
            return;
        }

        // Detectar chave estrangeira: campo [ref: > tabela.campo]
        if (preg_match('/(\w+)\s+\w+.*\[ref:\s*>\s*(\w+)\.(\w+)\]/', $line, $matches)) {
            $fieldName = $matches[1];
            $foreignTable = $matches[2];
            $foreignField = $matches[3];

            // Registrar dependência
            if (!isset($this->dependencies[$table])) {
                $this->dependencies[$table] = [];
            }
            
            if (!in_array($foreignTable, $this->dependencies[$table])) {
                $this->dependencies[$table][] = $foreignTable;
            }

            $fields[] = [
                'name' => $fieldName,
                'type' => 'integer',
                'foreign_key' => [
                    'table' => $foreignTable,
                    'field' => $foreignField
                ]
            ];
        } else {
            // Campo normal
            if (preg_match('/(\w+)\s+(\w+)/', $line, $matches)) {
                $fieldName = $matches[1];
                $fieldType = $matches[2];
                
                $fields[] = [
                    'name' => $fieldName,
                    'type' => $fieldType,
                    'foreign_key' => null
                ];
            }
        }
    }

    /**
     * Ordena tabelas respeitando dependências (topological sort)
     */
    private function orderTablesByDependencies(): void
    {
        $this->orderedTables = [];
        $visited = [];
        $visiting = [];

        foreach ($this->tables as $table => $fields) {
            if (!isset($visited[$table])) {
                $this->visitTable($table, $visited, $visiting);
            }
        }
    }

    /**
     * Visita uma tabela e suas dependências (DFS)
     */
    private function visitTable(string $table, array &$visited, array &$visiting): void
    {
        if (isset($visiting[$table])) {
            $this->warn("⚠️  Ciclo de dependência detectado envolvendo: {$table}");
            return;
        }

        if (isset($visited[$table])) {
            return;
        }

        $visiting[$table] = true;

        // Visitar dependências primeiro
        if (isset($this->dependencies[$table])) {
            foreach ($this->dependencies[$table] as $dependency) {
                if (isset($this->tables[$dependency])) {
                    $this->visitTable($dependency, $visited, $visiting);
                }
            }
        }

        unset($visiting[$table]);
        $visited[$table] = true;
        $this->orderedTables[] = $table;
    }

    /**
     * Exibe a ordem de criação
     */
    private function displayOrder(): void
    {
        $this->newLine();
        $this->info('📋 Ordem de criação dos domínios:');
        $this->newLine();

        $phase1 = [];
        $phase2 = [];

        foreach ($this->orderedTables as $table) {
            if (empty($this->dependencies[$table] ?? [])) {
                $phase1[] = $table;
            } else {
                $phase2[] = $table;
            }
        }

        $this->info('FASE 1: Domínios SEM chaves estrangeiras');
        $this->line('─' . str_repeat('─', 50));
        foreach ($phase1 as $index => $table) {
            $domainName = $this->getDomainName($table);
            $this->line(sprintf('  %d. %s (%s)', $index + 1, $domainName, $table));
        }

        if (!empty($phase2)) {
            $this->newLine();
            $this->info('FASE 2: Domínios COM chaves estrangeiras');
            $this->line('─' . str_repeat('─', 50));
            foreach ($phase2 as $index => $table) {
                $domainName = $this->getDomainName($table);
                $deps = implode(', ', $this->dependencies[$table] ?? []);
                $this->line(sprintf('  %d. %s (%s) → depende de: %s', 
                    count($phase1) + $index + 1, 
                    $domainName, 
                    $table,
                    $deps ?: 'nenhuma'
                ));
            }
        }

        $this->newLine();
    }

    /**
     * Gera os domínios na ordem correta
     */
    private function generateDomains(): void
    {
        $this->newLine();
        $this->info('🔨 Iniciando geração dos domínios...');
        $this->newLine();

        foreach ($this->orderedTables as $index => $table) {
            $domainName = $this->getDomainName($table);
            $modelName = Str::studly(Str::singular($table));
            
            $this->info(sprintf('[%d/%d] Gerando domínio: %s', 
                $index + 1, 
                count($this->orderedTables), 
                $domainName
            ));

            // Construir schema
            $schema = $this->buildSchema($table);
            
            // Construir foreign keys
            $foreignKeys = $this->buildForeignKeys($table);

            // Criar configuração JSON
            $config = [
                'domain' => $domainName,
                'model' => $modelName,
                'schema' => $schema,
                'foreignKeys' => $foreignKeys,
                'generateCompleteStructure' => true,
                'generateTests' => false,
                'generateDocs' => false,
                'force' => $this->option('force'),
            ];

            // Executar geração
            $this->generateDomain($config);

            $this->info("✅ Domínio '{$domainName}' criado com sucesso!");
            $this->newLine();
        }
    }

    /**
     * Constrói o schema para uma tabela
     */
    private function buildSchema(string $table): string
    {
        $fields = $this->tables[$table] ?? [];
        $schemaParts = [];

        foreach ($fields as $field) {
            // Ignorar id e campos de timestamp padrão
            if (in_array($field['name'], ['id', 'created_at', 'updated_at', 'deleted_at'])) {
                continue;
            }

            // Se for chave estrangeira, usar integer
            if ($field['foreign_key']) {
                $schemaParts[] = $field['name'] . '=integer,req';
            } else {
                $type = $this->mapDbmlTypeToSchema($field['type']);
                $schemaParts[] = $field['name'] . '=' . $type;
            }
        }

        return implode(';', $schemaParts);
    }

    /**
     * Constrói array de foreign keys para uma tabela
     */
    private function buildForeignKeys(string $table): array
    {
        $fields = $this->tables[$table] ?? [];
        $foreignKeys = [];

        foreach ($fields as $field) {
            if ($field['foreign_key']) {
                $foreignTable = $field['foreign_key']['table'];
                $foreignModel = Str::studly(Str::singular($foreignTable));
                $domainName = $this->getDomainName($foreignTable);

                $foreignKeys[] = [
                    'domain' => $domainName,
                    'model' => $foreignModel,
                    'relation' => 'belongsTo',
                    'required' => true // Foreign keys são obrigatórias por padrão
                ];
            }
        }

        return $foreignKeys;
    }

    /**
     * Mapeia tipos DBML para tipos do schema
     */
    private function mapDbmlTypeToSchema(string $dbmlType): string
    {
        return match (strtolower($dbmlType)) {
            'varchar', 'char' => 'string',
            'text', 'tinytext' => 'text',
            'integer', 'int' => 'integer',
            'float', 'double' => 'float',
            'decimal' => 'decimal',
            'boolean', 'bool' => 'boolean',
            'date' => 'date',
            'datetime', 'timestamp' => 'datetime',
            'json' => 'json',
            'enum' => 'string',
            default => 'string',
        };
    }

    /**
     * Obtém o nome do domínio a partir do nome da tabela
     */
    private function getDomainName(string $table): string
    {
        $prefix = $this->option('domain-prefix');
        $domainName = Str::studly(Str::singular($table));
        
        if ($prefix) {
            return $prefix . $domainName;
        }
        
        return $domainName;
    }

    /**
     * Executa a geração do domínio
     */
    private function generateDomain(array $config): void
    {
        $params = [
            '--domain' => true,
            '--config' => json_encode($config),
        ];

        if ($this->option('force')) {
            $params['--force'] = true;
        }

        if ($this->option('skip-frontend')) {
            $params['--skip-frontend'] = true;
        }

        if ($this->option('skip-backend')) {
            $params['--skip-backend'] = true;
        }

        Artisan::call('generate:crud', $params);
        
        // Exibir output se houver erros
        $output = Artisan::output();
        if (!empty(trim($output))) {
            $this->line($output);
        }
    }
}