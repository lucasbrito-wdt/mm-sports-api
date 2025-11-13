<?php

namespace App\Console\Commands\Generator;

use App\Console\Commands\Generator\Generators\BackEnd\ControllerGenerator;
use App\Console\Commands\Generator\Generators\BackEnd\MigrationGenerator;
use App\Console\Commands\Generator\Generators\BackEnd\ModelGenerator;
use App\Console\Commands\Generator\Generators\BackEnd\SeederGenerator;
use App\Console\Commands\Generator\Generators\BackEnd\ServiceGenerator;
use App\Console\Commands\Generator\Generators\FrontEnd\CriarGenerator;
use App\Console\Commands\Generator\Generators\FrontEnd\EditarGenerator;
use App\Console\Commands\Generator\Generators\FrontEnd\FormGenerator;
use App\Console\Commands\Generator\Generators\FrontEnd\FrontendPathTrait;
use App\Console\Commands\Generator\Generators\FrontEnd\ListGenerator;
use App\Console\Commands\Generator\Generators\FrontEnd\ServiceGenerator as FrontEndServiceGenerator;
use App\Console\Commands\Generator\Generators\FrontEnd\StoreGenerator;
use App\Console\Commands\Generator\Generators\FrontEnd\TypesGenerator;
use App\Console\Commands\Generator\Generators\Utils\FrontendUtils;
use App\Console\Commands\Generator\Utils\AbilityManager;
use App\Console\Commands\Generator\Utils\ModelRelationsManager;
use App\Console\Commands\Generator\Utils\RollbackLogger;
use App\Console\Commands\Generator\Utils\RouteManager;
use App\Console\Commands\Generator\Utils\SummaryBuilder;
use App\Console\Commands\Generator\Utils\TemplateManager;
use App\Console\Commands\Generator\Validators\RelationshipValidator;
use App\Console\Commands\Generator\Validators\SchemaValidator;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Console\Command\Command as CommandAlias;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\error;
use function Laravel\Prompts\select;
use function Laravel\Prompts\text;

class CrudGenerator extends Command
{
    use FrontendPathTrait;

    protected $signature = 'generate:crud {--force} {--skip-frontend} {--skip-backend} {--with-tests} {--with-docs} {--config=} {--domain : Gera um domínio em vez de um CRUD} {--rollback : Desfaz todas as alterações/criações feitas pelo gerador}';

    protected $description = 'Gera um CRUD completo com backend e frontend ou um domínio completo';

    private array $config = [];

    private array $foreigners = [];

    private bool $isDomainGenerator = false;

    private string $rollbackLogPath = '';

    private array $rollbackLog = [];

    private string $currentSessionId = '';

    public function __construct(
        private readonly TemplateManager $templateManager,
        private readonly SummaryBuilder $summaryBuilder,
        private readonly SchemaValidator $schemaValidator,
        private readonly RelationshipValidator $relationshipValidator,
        private readonly ModelRelationsManager $modelRelationsManager,
        private readonly AbilityManager $abilityManager,
        private readonly RouteManager $routeManager,
        private readonly RollbackLogger $rollbackLogger,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->rollbackLogPath = storage_path('framework/rollback/rollback_log.json');
        if ($this->option('rollback')) {
            $this->runRollback();

            return CommandAlias::SUCCESS;
        }

        $this->isDomainGenerator = $this->option('domain');

        if ($this->isDomainGenerator) {
            $this->info("\n\n🚀 Gerador de Domínio\n\n");
        } else {
            $this->info("\n\n🚀 Gerador de Crud\n\n");
        }

        // Verificar se foi fornecida uma configuração via JSON ou arquivo externo
        if ($configJson = $this->option('config')) {
            try {
                // Remove aspas simples ou duplas que podem ter vindo do terminal
                $configJson = trim($configJson, "'\"");

                // Verifica se o valor começa com '@', indicando que é um caminho para arquivo
                if (str_starts_with($configJson, '@')) {
                    $filePath = substr($configJson, 1);
                    if (! file_exists($filePath)) {
                        throw new \Exception("Arquivo de configuração não encontrado: {$filePath}");
                    }

                    $configJson = file_get_contents($filePath);
                }

                // Tenta decodificar o JSON
                $this->config = json_decode($configJson, true, 512, JSON_THROW_ON_ERROR);

                // Processar configuração de anexo para modo config-driven
                $this->processAttachConfig();

                $this->info('✅ Configuração carregada com sucesso via --config.');
            } catch (\Throwable $e) {
                $this->error('❌ Erro ao processar configuração: ' . $e->getMessage());

                return CommandAlias::FAILURE;
            }
        } else {
            // Fallback: modo interativo ou default
            $this->gatherInput();
        }

        // Iniciar sessão de rollback após o config ser carregado
        $action = $this->isDomainGenerator ? 'generate_domain' : 'generate_crud';
        $domain = $this->config['domain'] ?? 'unknown';

        $this->currentSessionId = $this->rollbackLogger->startSession($action, $domain, [
            'is_domain_generator' => $this->isDomainGenerator,
            'skip_frontend' => $this->option('skip-frontend'),
            'skip_backend' => $this->option('skip-backend'),
            'with_tests' => $this->option('with-tests'),
            'with_docs' => $this->option('with-docs'),
        ]);

        if ($this->isDomainGenerator) {
            // Montar e exibir resumo para domínio
            $this->info('📋 Resumo do Domínio a ser gerado:');
            $this->line("Domínio: {$this->config['domain']}");

            // Mostrar CRUD principal se existir
            if (isset($this->config['model'])) {
                $this->line("CRUD Principal: {$this->config['model']}");
            }

            // Mostrar CRUDs adicionais se existirem
            if (isset($this->config['crud']) && is_array($this->config['crud'])) {
                $this->line('CRUDs Adicionais:');
                foreach ($this->config['crud'] as $crud) {
                    $this->line("  - {$crud['model']}");
                }
            }

            if (! $this->option('force') && ! confirm('Confirma a geração do Domínio?', true)) {
                $this->info('❌ Operação cancelada pelo usuário.');

                return CommandAlias::FAILURE;
            }

            $this->generateDomain();

            if (! $this->option('skip-frontend')) {
                $this->generateFrontend();
            }

            $this->info('✅ Domínio gerado com sucesso!');
        } else {
            // Montar e exibir resumo para CRUD
            $summary = $this->summaryBuilder->buildSummary($this->config);
            $this->info('📋 Resumo do CRUD a ser gerado:');
            $this->line($summary);

            if (! $this->option('force') && ! confirm('Confirma a geração do CRUD?', true)) {
                $this->info('❌ Operação cancelada pelo usuário.');

                return CommandAlias::FAILURE;
            }

            $this->generateCrud();

            if (! $this->option('skip-frontend')) {
                $this->generateFrontend();
            }

            $this->info('✅ CRUD gerado com sucesso!');
        }

        // Finalizar sessão de rollback
        $this->rollbackLogger->endSession($this->currentSessionId, 'completed');

        return CommandAlias::SUCCESS;
    }

    private function gatherInput(): void
    {
        // Se for gerador de domínio, coletamos menos informações
        if ($this->isDomainGenerator) {
            $this->gatherDomainInput();
        } else {
            $this->gatherCrudInput();
        }
    }

    private function gatherDomainInput(): void
    {
        // Nome do Domínio
        $this->config['domain'] = text(
            label: 'Nome do Domínio',
            placeholder: 'Ex: Products, Users, Orders',
            validate: function ($value) {
                if (empty($value)) {
                    return 'O nome do Domínio é obrigatório';
                }

                if (! preg_match('/^[A-Z][a-zA-Z]+$/', $value)) {
                    return 'O nome deve começar com letra maiúscula e conter apenas letras';
                }

                // Verificar se o domínio já existe
                if (in_array($value, $this->getAvailableDomains())) {
                    return "O domínio '$value' já existe";
                }

                return true;
            }
        );

        // Confirmar geração de estrutura completa
        $this->config['generateCompleteStructure'] = confirm('Deseja gerar uma estrutura completa para o domínio?', true);

        if ($this->config['generateCompleteStructure']) {
            // Solicitar nome da model base
            $this->config['model'] = text(
                label: 'Nome da Model base para o domínio',
                placeholder: 'Ex: Product, User, Order',
                default: $this->config['domain'],
                validate: function ($value) {
                    if (empty($value)) {
                        return 'O nome da Model base é obrigatório';
                    }

                    if (! preg_match('/^[A-Z][a-zA-Z]+$/', $value)) {
                        return 'O nome deve começar com letra maiúscula e conter apenas letras';
                    }

                    return true;
                }
            );

            // Schema da Migration base
            $this->config['schema'] = text(
                label: 'Schema da Migration base | ex: name=string,100,req;price=decimal,10,2,req',
                validate: function ($value) {
                    return $this->schemaValidator->validate($value);
                },
                hint: 'Formato: campo=tipo,tamanho,req;campo2=tipo2,...'
            );
        }

        // Gerar chaves estrangeiras?
        // Verificar se existem outros domínios disponíveis para criar relacionamentos
        $domainsAvailable = $this->getAvailableDomains();
        if (empty($domainsAvailable)) {
            $this->info('  ℹ️ Não há outros domínios disponíveis para criar relacionamentos.');
            $this->config['foreignKeys'] = [];
        } else {
            $this->gatherForeignKeys();
        }

        // Gerar testes para o domínio?
        if (! $this->option('with-tests')) {
            $this->config['generateTests'] = confirm('Deseja gerar testes para este Domínio?', false);
        } else {
            $this->config['generateTests'] = true;
        }

        // Gerar documentação para o domínio?
        if (! $this->option('with-docs')) {
            $this->config['generateDocs'] = confirm('Deseja gerar documentação para este Domínio?', false);
        } else {
            $this->config['generateDocs'] = true;
        }
    }

    private function gatherCrudInput(): void
    {
        // Selecionar Domínio
        $domainsDir = $this->getAvailableDomains();
        $this->config['domain'] = select(
            label: 'Selecione o domínio',
            options: $domainsDir,
            required: true
        );

        // Nome do CRUD
        $this->config['name'] = text(
            label: 'Nome do CRUD',
            placeholder: 'Ex: Product, User, Order',
            validate: function ($value) {
                if (empty($value)) {
                    return 'O nome do CRUD é obrigatório';
                }

                if (! preg_match('/^[A-Z][a-zA-Z]+$/', $value)) {
                    return 'O nome deve começar com letra maiúscula e conter apenas letras';
                }

                return true;
            }
        );

        // Nome da Model (default: mesmo nome do CRUD)
        $this->config['model'] = text(
            label: 'Nome da Model',
            default: $this->config['name'],
            validate: fn($value) => empty($value) ? 'O nome da Model é obrigatório' : true
        );

        // Nome da Migration
        $defaultMigration = 'create_' . Str::snake(Str::plural($this->config['name'])) . '_table';
        $this->config['migration'] = text(
            label: 'Nome da Migration',
            default: $defaultMigration,
            validate: fn($value) => empty($value) ? 'O nome da Migration é obrigatório' : true
        );

        // Nome do Service (default: NomeCRUDService)
        $this->config['service'] = text(
            label: 'Nome do Service',
            default: $this->config['name'] . 'Service',
            validate: fn($value) => empty($value) ? 'O nome do Service é obrigatório' : true
        );

        // Schema da Migration
        $this->config['schema'] = text(
            label: 'Schema da Migration | ex: name=string,100,req;price=decimal,10,2,req',
            validate: function ($value) {
                return $this->schemaValidator->validate($value);
            },
            hint: 'Formato: campo=tipo,tamanho,req;campo2=tipo2,...'
        );

        // Perguntar se deve anexar ao formulário do domínio ou criar nova estrutura
        $this->config['attachToForm'] = $this->shouldAttachToForm();

        // Chaves estrangeiras
        $this->gatherForeignKeys();

        // Confirmar geração de testes
        if (! $this->option('with-tests')) {
            $this->config['generateTests'] = confirm('Deseja gerar testes para este CRUD?', false);
        } else {
            $this->config['generateTests'] = true;
        }

        // Confirmar geração de documentação
        if (! $this->option('with-docs')) {
            $this->config['generateDocs'] = confirm('Deseja gerar documentação para este CRUD?', false);
        } else {
            $this->config['generateDocs'] = true;
        }
    }

    private function gatherForeignKeys(): void
    {
        // Inicializa o array de chaves estrangeiras
        $this->foreigners = [];
        $this->config['foreignKeys'] = [];

        $count = 0;
        $hasForeignKeys = confirm('Deseja adicionar chaves estrangeiras?', false);

        while ($hasForeignKeys) {
            // Domínio da FK
            $domains = $this->getAvailableDomains();

            // Se não houver domínios disponíveis (isso pode acontecer ao criar o primeiro domínio)
            if (empty($domains)) {
                $this->warn('  ⚠️ Não há domínios disponíveis para criar relações.');
                break;
            }

            $fkDomain = select(
                label: 'Domínio da chave estrangeira',
                options: $domains
            );

            // Model da FK
            $fkModels = $this->getAvailableModels($fkDomain);

            // Se não houver modelos disponíveis no domínio selecionado
            if (empty($fkModels)) {
                $this->warn("  ⚠️ Não há modelos disponíveis no domínio '{$fkDomain}'.");
                if (confirm('Deseja selecionar outro domínio?', true)) {
                    continue;
                } else {
                    break;
                }
            }

            $fkModel = select(
                label: 'Model da chave estrangeira',
                options: $fkModels
            );

            // Tipo de relação
            $relationType = select(
                label: 'Tipo de relação',
                options: [
                    'belongsTo' => 'Pertence a (belongsTo)',
                    'hasMany' => 'Possui muitos (hasMany)',
                    'hasOne' => 'Possui um (hasOne)',
                    'belongsToMany' => 'Pertence a muitos (belongsToMany)',
                ],
                default: 'belongsTo'
            );

            // Obrigatoriedade
            $isRequired = confirm('Esta relação é obrigatória?', true);

            // Montar dados do relacionamento
            $relation = [
                'domain' => $fkDomain,
                'model' => $fkModel,
                'relation' => $relationType,
                'required' => $isRequired,
            ];

            // Validar o relacionamento
            $validationResult = $this->relationshipValidator->validate($relation);

            if ($validationResult !== true) {
                error($validationResult);
                if (! confirm('Deseja tentar adicionar este relacionamento novamente?', true)) {
                    continue;
                } else {
                    // Continua o loop sem incrementar $count para tentar novamente
                    continue;
                }
            }

            // Se passou na validação, adiciona o relacionamento
            $this->foreigners[$count] = $relation;

            $count++;
            $hasForeignKeys = confirm('Deseja adicionar outra chave estrangeira?', false);
        }

        $this->config['foreignKeys'] = $this->foreigners;

        // Validar todos os relacionamentos como conjunto
        if (! empty($this->foreigners)) {
            $finalValidation = $this->relationshipValidator->validateAll($this->foreigners);
            if ($finalValidation !== true) {
                error($finalValidation);
                if (confirm('Os relacionamentos possuem problemas. Deseja limpar todos e adicionar novamente?', false)) {
                    $this->foreigners = [];
                    $this->config['foreignKeys'] = [];
                    $this->gatherForeignKeys(); // Chamada recursiva para recomeçar
                }
            }
        }
    }

    /**
     * Processa a configuração de anexo para modo config-driven (JSON).
     */
    private function processAttachConfig(): void
    {
        // Se não é domínio generator e não tem attachToForm definido, definir como false
        if (!$this->isDomainGenerator && !isset($this->config['attachToForm'])) {
            $this->config['attachToForm'] = false;
        }
    }

    /**
     * Pergunta ao usuário se deve anexar os campos ao formulário do domínio
     * ou criar uma nova estrutura de formulário.
     */
    private function shouldAttachToForm(): bool
    {
        // Verificar se existe um formulário principal no domínio
        $domainFormExists = $this->checkDomainFormExists();

        if (!$domainFormExists) {
            $this->info('  ℹ️ Não foi encontrado um formulário principal no domínio. Uma nova estrutura será criada.');
            return false;
        }

        // Perguntar se deve anexar ao formulário existente
        $attachChoice = select(
            label: 'Como deseja integrar este CRUD ao formulário do domínio?',
            options: [
                'nova' => 'Criar nova estrutura de formulário',
                'anexar' => 'Anexar campos ao formulário existente do domínio'
            ],
            default: 'anexar'
        );

        return $attachChoice === 'anexar';
    }

    /**
     * Verifica se existe um formulário principal no domínio.
     */
    private function checkDomainFormExists(): bool
    {
        // Procurar por arquivos de formulário no domínio
        $domainPath = $this->getFrontendPath() . '/pages/' . Str::kebab($this->config['domain']) . '/components';

        if (!File::exists($domainPath)) {
            return false;
        }

        // Procurar por arquivos *Form.vue
        $formFiles = glob($domainPath . '/*Form.vue');

        return !empty($formFiles);
    }

    private function generateCrud(): void
    {
        if ($this->option('skip-backend')) {
            $this->info('🔹 Geração de backend ignorada conforme solicitado.');

            return;
        }

        $this->info('🔹 Gerando componentes de Backend...');

        // Gerar Model
        $modelGenerator = app(ModelGenerator::class);
        $modelPath = app_path("Domains/{$this->config['domain']}/Models/{$this->config['model']}.php");
        if ($modelGenerator->generate($this->config)) {
            $this->info('  ✓ Model gerada com sucesso');
            if (file_exists($modelPath)) {
                $this->logCreatedFile($modelPath);
            }
        }

        // Processar relações bidirecionais entre modelos
        if (! empty($this->config['foreignKeys'])) {
            $this->modelRelationsManager->createRelationships(
                $this->config['foreignKeys'],
                $this->config['domain'],
                $this->config['model']
            );
            $this->info('  ✓ Relações entre modelos configuradas com sucesso');
        }

        // Gerar Migration
        $migrationGenerator = app(MigrationGenerator::class);
        $migrationDir = app_path("Domains/{$this->config['domain']}/Migrations");
        $tableName = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($this->config['model']));
        $migrationFilePattern = $migrationDir . '/*_create_' . $tableName . '_table.php';
        $beforeFiles = glob($migrationFilePattern);
        if ($migrationGenerator->generate($this->config)) {
            $this->info('  ✓ Migration gerada com sucesso');
            // Detecta o novo arquivo gerado
            $afterFiles = glob($migrationFilePattern);
            $newFiles = array_diff($afterFiles, $beforeFiles);
            foreach ($newFiles as $file) {
                $this->logCreatedFile($file);
            }
        } else {
            $this->error('  ✗ Falha ao gerar migration');
        }

        // Gerar Service
        $serviceGenerator = app(ServiceGenerator::class);
        $servicePath = app_path("Domains/{$this->config['domain']}/Services/{$this->config['service']}.php");
        if ($serviceGenerator->generate($this->config)) {
            $this->info('  ✓ Service gerado com sucesso');
            if (file_exists($servicePath)) {
                $this->logCreatedFile($servicePath);
            }
        }

        // Gerar Controller
        $controllerGenerator = app(ControllerGenerator::class);
        if ($controllerGenerator->generate($this->config)) {
            $this->info('  ✓ Controller gerado com sucesso');

            // Registrar arquivos do Controller e Request criados para rollback
            $controllerPath = app_path("Domains/{$this->config['domain']}/Controllers/{$this->config['model']}Controller.php");
            if (file_exists($controllerPath)) {
                $this->logCreatedFile($controllerPath);
            }

            $requestPath = app_path("Domains/{$this->config['domain']}/Requests/{$this->config['model']}Request.php");
            if (file_exists($requestPath)) {
                $this->logCreatedFile($requestPath);
            }
        }

        // Gerar rotas automaticamente após o controller
        if ($this->routeManager->createDomainRoutes($this->config['domain'], $this->config['model'])) {
            $this->info('  ✓ Rotas geradas automaticamente');
            // Registrar arquivos de rotas criados para rollback
            $routeFilePath = base_path('routes/domains/' . \Illuminate\Support\Str::kebab($this->config['domain']) . '.php');
            if (file_exists($routeFilePath)) {
                $this->logCreatedFile($routeFilePath);
            }
            // Registrar modificação do api.php para rollback
            $apiRoutesPath = base_path('routes/api.php');
            if (file_exists($apiRoutesPath)) {
                $this->logModifiedFile($apiRoutesPath);
            }
        } else {
            $this->error('  ✗ Falha ao gerar rotas automaticamente');
        }

        // Gerar Seeder
        $seederGenerator = app(SeederGenerator::class);
        if ($seederGenerator->generate($this->config)) {
            $this->info('  ✓ Seeder gerado com sucesso');

            // Registrar arquivo do Seeder criado para rollback
            $seederPath = app_path("Domains/{$this->config['domain']}/Seeders/{$this->config['model']}Seeder.php");
            if (file_exists($seederPath)) {
                $this->logCreatedFile($seederPath);
            }

            // Registrar modificação do DatabaseSeeder.php
            $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');
            if (file_exists($databaseSeederPath)) {
                $this->logModifiedFile($databaseSeederPath);
            }
        }

        // Gerar abilities
        $configPath = config_path('permission_list.php');
        if (file_exists($configPath)) {
            $this->logModifiedFile($configPath);
        }
        $this->abilityManager->createAbilityAndConfig($this->config['domain']);
        $this->info('  ✓ Abilities e config/permission_list.php atualizados');
    }

    /**
     * Gera a estrutura de um novo domínio
     */
    private function generateDomain(): void
    {
        $domainName = $this->config['domain'];
        $this->info('🔹 Gerando estrutura para o Domínio: ' . $domainName);

        // Criando estrutura de diretórios
        $directories = $this->getDomainDirectoryStructure();
        $baseDomainPath = app_path("Domains/{$domainName}");

        // Verificar se o diretório já existe
        if (file_exists($baseDomainPath)) {
            $this->error("O domínio {$domainName} já existe!");

            return;
        }

        // Criar diretórios base
        if (! File::exists($baseDomainPath)) {
            if (File::makeDirectory($baseDomainPath, 0755, true)) {
                $this->info("  ✓ Diretório base do domínio criado: {$domainName}");
                $this->logCreatedDirectory($baseDomainPath);
            } else {
                $this->error('  ✗ Falha ao criar diretório base do domínio');

                return;
            }
        }

        // Criar subdiretórios
        foreach ($directories as $directory) {
            $path = $baseDomainPath . '/' . $directory;
            if (! File::exists($path)) {
                if (File::makeDirectory($path, 0755, true)) {
                    $this->info("  ✓ Diretório criado: {$directory}");
                    $this->logCreatedDirectory($path);
                } else {
                    $this->error("  ✗ Falha ao criar diretório: {$directory}");
                }
            }
        }

        $this->info('🔹 Gerando arquivos base para o Domínio...');

        // Gerar arquivos base se solicitado
        if ($this->config['generateCompleteStructure']) {
            // Gerar o CRUD principal se existir
            if (isset($this->config['model'])) {
                $this->info('🔹 Gerando CRUD principal...');
                $this->generateBaseModelForDomain();
                $this->generateBaseMigrationForDomain();
                $this->generateBaseServiceForDomain();
                $this->generateBaseControllerForDomain();
                $this->generateBaseSeederForDomain();
                $this->generateAbilitiesForDomain();

                if ($this->config['generateTests'] ?? false) {
                    $this->generateBaseTestForDomain();
                }
            }

            // Gerar CRUDs adicionais se existirem
            if (isset($this->config['crud']) && is_array($this->config['crud'])) {
                $this->info('🔹 Gerando CRUDs adicionais...');
                foreach ($this->config['crud'] as $index => $crudConfig) {
                    $this->info("  ↳ Gerando CRUD: {$crudConfig['model']}");
                    $this->generateAdditionalCrud($crudConfig);
                }
            }

            // Recapitular tudo o que foi gerado
            $this->info("\n✅ Domínio $domainName gerado com sucesso com a estrutura completa:");

            // Mostrar CRUD principal
            if (isset($this->config['model'])) {
                $this->line('📦 CRUD Principal:');
                $this->line("   • Model: {$this->config['model']}");
                $this->line('   • Migration: create_' . Str::snake(Str::plural($this->config['model'])) . '_table');
                $this->line("   • Controller: {$this->config['model']}Controller");
                $this->line("   • Service: {$this->config['model']}Service");
                $this->line("   • Seeder: {$this->config['model']}Seeder");
            }

            // Mostrar CRUDs adicionais
            if (isset($this->config['crud']) && is_array($this->config['crud'])) {
                $this->line("\n📦 CRUDs Adicionais:");
                foreach ($this->config['crud'] as $crudConfig) {
                    $this->line("   • {$crudConfig['model']} (Model, Migration, Controller, Service, Seeder)");
                }
            }

            // Mostrar informações sobre chaves estrangeiras do CRUD principal
            if (! empty($this->config['foreignKeys'])) {
                $this->line("\n🔗 Relações do CRUD Principal:");
                foreach ($this->config['foreignKeys'] as $fk) {
                    $relationType = $fk['relation'] ?? 'belongsTo';
                    $relationTypeText = match ($relationType) {
                        'belongsTo' => 'pertence a',
                        'hasMany' => 'possui muitos',
                        'hasOne' => 'possui um',
                        'belongsToMany' => 'pertence a muitos',
                        default => $relationType
                    };
                    $this->line("   • {$this->config['model']} {$relationTypeText} {$fk['model']} do domínio {$fk['domain']}");
                }
            }
        } else {
            $this->info("\n✅ Domínio $domainName gerado com sucesso (somente estrutura básica)");
            $this->line("   Use 'php artisan generate:crud' para adicionar CRUDs a este domínio");
        }
    }

    private function generateFrontend(): void
    {
        $this->info('🔹 Gerando componentes de Frontend...');

        $domainName = $this->config['domain'];

        // Criando estrutura de diretórios
        $directories = $this->getFrontendDirectoryStructure();
        $baseCrudPath = $this->getFrontendPath() . '/pages/' . Str::kebab($domainName);

        // Criar diretórios base
        if (! File::exists($baseCrudPath)) {
            if (File::makeDirectory($baseCrudPath, 0755, true)) {
                $this->info("  ✓ Diretório base do domínio criado: {$domainName}");
                $this->logCreatedDirectory($baseCrudPath);
            } else {
                $this->error('  ✗ Falha ao criar diretório base do domínio');

                return;
            }
        }

        // Criar subdiretórios
        foreach ($directories as $directory) {
            $path = $baseCrudPath . '/' . $directory;
            if (! File::exists($path)) {
                if (File::makeDirectory($path, 0755, true)) {
                    $this->info("  ✓ Diretório criado: {$directory}");
                    $this->logCreatedDirectory($path);
                } else {
                    $this->error("  ✗ Falha ao criar diretório: {$directory}");
                }
            }
        }

        // Gerar Types
        $typesGenerator = app(TypesGenerator::class);
        if ($typesGenerator->generate($this->config)) {
            $this->info('  ✓ Types gerados com sucesso');

            // Registrar arquivo gerado para rollback
            $typesPath = $this->getFrontendPath() . '/pages/' . Str::kebab($this->config['domain']) . '/types.ts';
            if (file_exists($typesPath)) {
                $this->logCreatedFile($typesPath);
            }
        }

        // Gerar Store
        $storeGenerator = app(StoreGenerator::class);
        if ($storeGenerator->generate($this->config)) {
            $this->info('  ✓ Store gerado com sucesso');

            // Registrar arquivo gerado para rollback
            $storePath = $this->getFrontendPath() . '/pages/' . Str::kebab($this->config['domain']) . '/stores/use' . Str::camel($this->config['model']) . 'Store.ts';
            if (file_exists($storePath)) {
                $this->logCreatedFile($storePath);
            }
        }

        // Gerar Service
        $serviceGenerator = app(FrontEndServiceGenerator::class);
        if ($serviceGenerator->generate($this->config)) {
            $this->info('  ✓ Service gerado com sucesso');

            // Registrar arquivo gerado para rollback
            $servicePath = $this->getFrontendPath() . '/pages/' . Str::kebab($this->config['domain']) . '/services/' . Str::camel($this->config['model']) . 'Service.ts';
            if (file_exists($servicePath)) {
                $this->logCreatedFile($servicePath);
            }
        }

        // Gerar Form
        $formGenerator = app(FormGenerator::class);

        // Adicionar configuração de anexo ao formulário
        $this->config['shouldAttach'] = $this->config['attachToForm'] ?? false;

        if ($formGenerator->generate($this->config)) {
            $this->info('  ✓ Formulário gerado com sucesso');

            // Registrar arquivo gerado para rollback
            $formPath = $this->getFrontendPath() . '/pages/' . Str::kebab($this->config['domain']) . "/components/{$this->config['model']}Form.vue";
            if (file_exists($formPath)) {
                $this->logCreatedFile($formPath);
            }
        }

        // Gerar List
        $listGenerator = app(ListGenerator::class);
        if ($listGenerator->generate($this->config)) {
            $this->info('  ✓ Listagem gerada com sucesso');

            // Registrar arquivo gerado para rollback
            $listPath = $this->getFrontendPath() . '/pages/' . Str::kebab($this->config['domain']) . '/index.vue';
            if (file_exists($listPath)) {
                $this->logCreatedFile($listPath);
            }
        }

        // Gerar Criar
        $criarGenerator = app(CriarGenerator::class);
        if ($criarGenerator->generate($this->config)) {
            $this->info('  ✓ Página de criação gerada com sucesso');

            // Registrar arquivo gerado para rollback
            $criarPath = $this->getFrontendPath() . '/pages/' . Str::kebab($this->config['domain']) . '/cadastrar/index.vue';
            if (file_exists($criarPath)) {
                $this->logCreatedFile($criarPath);
            }
        }

        // Gerar Editar
        $editarGenerator = app(EditarGenerator::class);
        if ($editarGenerator->generate($this->config)) {
            $this->info('  ✓ Página de edição gerada com sucesso');

            // Registrar arquivo gerado para rollback
            $criarPath = $this->getFrontendPath() . '/pages/' . Str::kebab($this->config['domain']) . '/editar/[id].vue';
            if (file_exists($criarPath)) {
                $this->logCreatedFile($criarPath);
            }
        }

        $frontendUtilsGenerator = app(FrontendUtils::class);
        $menuFilePath = $this->getFrontendPath() . '/navigation/vertical/index.ts';

        if ($frontendUtilsGenerator->addMenu($this->config)) {
            $this->info('  ✓ Adicionado ao menu com sucesso');

            $this->logModifiedFile($menuFilePath);
        }

        $abilityFile = $this->getFrontendPath() . '/configs/abilityConfig.ts';
        if ($frontendUtilsGenerator->addAbility($this->config)) {
            $this->info('  ✓ Abilities adicionadas com sucesso');

            $this->logModifiedFile($abilityFile);
        }

        // Executar ESLint
        $this->runEslint();
    }

    /**
     * Executa o ESLint para formatar os arquivos do frontend
     */
    private function runEslint(): void
    {
        try {
            $frontEndDir = str_replace('/src', '', $this->getFrontendPath());
            $command = sprintf(
                'cd %s && %s %s --fix',
                $frontEndDir,
                '.\\node_modules\\.bin\\eslint',
                '.\\src\\pages\\' . Str::kebab($this->config['domain']) . '\\**\\*.{ts,vue}'
            );

            $this->info('  🔸 Executando ESLint...');

            exec($command, $output, $returnCode);

            if ($returnCode === 0) {
                $this->info('  ✓ ESLint executado com sucesso');
            } else {
                $this->warn('  ⚠️ ESLint encontrou problemas, mas o CRUD foi gerado');
            }
        } catch (\Exception $e) {
            $this->warn('  ⚠️ Não foi possível executar o ESLint: ' . $e->getMessage());
        }
    }

    private function getAvailableDomains(): array
    {
        $domainsDir = [];
        foreach (collect(scandir(app_path() . '/Domains')) as $dir) {
            if (! in_array($dir, ['.', '..', 'Shared', 'ACL'])) {
                $domainsDir[] = $dir;
            }
        }

        return $domainsDir;
    }

    private function getAvailableModels(string $domain): array
    {
        $modelsDir = [];
        $modelsPath = app_path() . "/Domains/{$domain}/Models";

        if (file_exists($modelsPath)) {
            foreach (collect(scandir($modelsPath)) as $file) {
                if (! in_array($file, ['.', '..']) && pathinfo($file, PATHINFO_EXTENSION) === 'php') {
                    $modelsDir[] = pathinfo($file, PATHINFO_FILENAME);
                }
            }
        }

        return $modelsDir;
    }

    /**
     * Retorna a estrutura de diretórios para um domínio
     */
    private function getDomainDirectoryStructure(): array
    {
        $directories = [
            'Controllers',
            'Enums',
            'Migrations',
            'Models',
            'Requests',
            'Seeders',
            'Services',
        ];

        // Adicionar diretórios de teste apenas se generateTests estiver habilitado
        if ($this->config['generateTests'] ?? false) {
            $directories[] = 'Tests/Unit';
            $directories[] = 'Tests/Feature';
        }

        return $directories;
    }

    private function getFrontendDirectoryStructure(): array
    {
        return [
            'cadastrar',
            'editar',
            'components',
            'services',
            'stores',
        ];
    }

    /**
     * Gera o modelo base para o domínio
     */
    private function generateBaseModelForDomain(): void
    {
        $domainName = $this->config['domain'];
        $modelName = $this->config['model'];

        // Configuração para o gerador de modelo
        $modelConfig = [
            'domain' => $domainName,
            'model' => $modelName,
            'schema' => $this->config['schema'],
            'foreignKeys' => $this->config['foreignKeys'] ?? [],
        ];

        // Gerar Model
        $modelGenerator = app(ModelGenerator::class);
        $modelPath = app_path("Domains/{$domainName}/Models/{$modelName}.php");
        if ($modelGenerator->generate($modelConfig)) {
            $this->info('  ✓ Model base gerada com sucesso');
            if (file_exists($modelPath)) {
                $this->logCreatedFile($modelPath);
            }
        }

        // Processar relações bidirecionais entre modelos
        if (! empty($this->config['foreignKeys'])) {
            $this->modelRelationsManager->createRelationships(
                $this->config['foreignKeys'],
                $this->config['domain'],
                $this->config['model']
            );
            $this->info('  ✓ Relações entre modelos configuradas com sucesso');
        }
    }

    /**
     * Gera a migration base para o domínio
     */
    private function generateBaseMigrationForDomain(): void
    {
        $domainName = $this->config['domain'];
        $modelName = $this->config['model'];

        // Configuração para o gerador de migration
        $migrationConfig = [
            'domain' => $domainName,
            'model' => $modelName,
            'migration' => 'create_' . Str::snake(Str::plural($modelName)) . '_table',
            'schema' => $this->config['schema'],
            'foreignKeys' => $this->config['foreignKeys'] ?? [],
        ];

        // Gerar Migration
        $migrationGenerator = app(MigrationGenerator::class);
        $migrationDir = app_path("Domains/{$domainName}/Migrations");
        $tableName = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($modelName));
        $migrationFilePattern = $migrationDir . '/*_create_' . $tableName . '_table.php';
        $beforeFiles = glob($migrationFilePattern);
        if ($migrationGenerator->generate($migrationConfig)) {
            $this->info('  ✓ Migration base gerada com sucesso em ' . database_path('migrations'));
            // Detecta o novo arquivo gerado
            $afterFiles = glob($migrationFilePattern);
            $newFiles = array_diff($afterFiles, $beforeFiles);
            foreach ($newFiles as $file) {
                $this->logCreatedFile($file);
            }
        } else {
            $this->error('  ✗ Falha ao gerar migration');
        }
    }

    /**
     * Gera o serviço base para o domínio
     */
    private function generateBaseServiceForDomain(): void
    {
        $domainName = $this->config['domain'];
        $modelName = $this->config['model'];
        $serviceName = $modelName . 'Service';

        // Configuração para o gerador de serviço
        $serviceConfig = [
            'domain' => $domainName,
            'model' => $modelName,
            'service' => $serviceName,
            'foreignKeys' => $this->config['foreignKeys'] ?? [],
        ];

        // Gerar Service
        $serviceGenerator = app(ServiceGenerator::class);
        $servicePath = app_path("Domains/{$domainName}/Services/{$serviceName}.php");
        if ($serviceGenerator->generate($serviceConfig)) {
            $this->info('  ✓ Service base gerado com sucesso');
            if (file_exists($servicePath)) {
                $this->logCreatedFile($servicePath);
            }
        }
    }

    /**
     * Gera o controller base para o domínio
     */
    private function generateBaseControllerForDomain(): void
    {
        $domainName = $this->config['domain'];
        $modelName = $this->config['model'];

        // Configuração para o gerador de controller
        $controllerConfig = [
            'domain' => $domainName,
            'model' => $modelName,
            'schema' => $this->config['schema'],
            'service' => $modelName . 'Service',
            'foreignKeys' => $this->config['foreignKeys'] ?? [],
        ];

        // Gerar Controller
        $controllerGenerator = app(ControllerGenerator::class);
        $controllerPath = app_path("Domains/{$domainName}/Controllers/{$modelName}Controller.php");
        if ($controllerGenerator->generate($controllerConfig)) {
            $this->info('  ✓ Controller base gerado com sucesso');
            if (file_exists($controllerPath)) {
                $this->logCreatedFile($controllerPath);
            }

            // Registrar arquivo do Request criado para rollback
            $requestPath = app_path("Domains/{$domainName}/Requests/{$modelName}Request.php");
            if (file_exists($requestPath)) {
                $this->logCreatedFile($requestPath);
            }

            // Gerar rotas automaticamente após o controller base
            if ($this->routeManager->createDomainRoutes($domainName, $modelName, [
                'foreignKeys' => $this->config['foreignKeys'] ?? [],
            ])) {
                $this->info('  ✓ Rotas base geradas automaticamente');
                // Registrar arquivos de rotas criados para rollback
                $routeFilePath = base_path('routes/domains/' . \Illuminate\Support\Str::kebab($domainName) . '.php');
                if (file_exists($routeFilePath)) {
                    $this->logCreatedFile($routeFilePath);
                }
                // Registrar modificação do api.php para rollback
                $apiRoutesPath = base_path('routes/api.php');
                if (file_exists($apiRoutesPath)) {
                    $this->logModifiedFile($apiRoutesPath);
                }
            } else {
                $this->error('  ✗ Falha ao gerar rotas base automaticamente');
            }
        }

        // Gerar Seeder
        $seederGenerator = app(SeederGenerator::class);
        if ($seederGenerator->generate($this->config)) {
            $this->info('  ✓ Seeder gerado com sucesso');
        }
    }

    /**
     * Gera testes base para o domínio
     */
    private function generateBaseTestForDomain(): void
    {
        $domainName = $this->config['domain'];
        $modelName = $this->config['model'];

        // Criar diretório para testes unitários
        $unitTestDir = app_path("Domains/{$domainName}/Tests/Unit");
        if (! file_exists($unitTestDir)) {
            mkdir($unitTestDir, 0755, true);
            $this->logCreatedDirectory($unitTestDir);
        }

        // Criar diretório para testes de integração
        $featureTestDir = app_path("Domains/{$domainName}/Tests/Feature");
        if (! file_exists($featureTestDir)) {
            mkdir($featureTestDir, 0755, true);
            $this->logCreatedDirectory($featureTestDir);
        }

        $this->info('  ✓ Estrutura de testes gerada com sucesso');
    }

    /**
     * Gera o seeder base para o domínio
     */
    private function generateBaseSeederForDomain(): void
    {
        $domainName = $this->config['domain'];
        $modelName = $this->config['model'];

        // Configuração para o gerador de seeder
        $seederConfig = [
            'domain' => $domainName,
            'model' => $modelName, // Garante que sempre exista a chave 'model'
            'foreignKeys' => $this->config['foreignKeys'] ?? [],
        ];

        // Gerar Seeder
        $seederGenerator = app(SeederGenerator::class);
        $seederPath = app_path("Domains/{$domainName}/Seeders/{$modelName}Seeder.php");
        if ($seederGenerator->generate($seederConfig)) {
            $this->info('  ✓ Seeder base gerado com sucesso');
            if (file_exists($seederPath)) {
                $this->logCreatedFile($seederPath);
            }

            // Registrar modificação do DatabaseSeeder.php
            $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');
            if (file_exists($databaseSeederPath)) {
                $this->logModifiedFile($databaseSeederPath);
            }
        } else {
            $this->error('  ✗ Falha ao gerar seeder');
        }
    }

    public function generateAbilitiesForDomain(): void
    {
        $domainName = $this->config['domain'];
        $modelName = $this->config['model'];

        // Criar abilities e atualizar config/permission_list.php
        $abilityManager = app(AbilityManager::class);
        $configPath = config_path('permission_list.php');
        if (file_exists($configPath)) {
            $this->logModifiedFile($configPath);
        }
        $abilityManager->createAbilityAndConfig($modelName, $domainName);
        $this->info('  ✓ Abilities e config/permission_list.php atualizados');
    }

    /**
     * Executa o rollback de todas as alterações/criações feitas pelo gerador
     */
    private function runRollback(): void
    {
        if (! file_exists($this->rollbackLogPath)) {
            $this->error('Nenhum log de rollback encontrado. Nada a desfazer.');

            return;
        }
        $this->info('🔄 Iniciando rollback das alterações/criações do gerador...');

        // Carregar log usando o novo sistema
        $legacyLog = $this->rollbackLogger->exportLegacyFormat();

        if (empty($legacyLog['created']) && empty($legacyLog['modified']) && empty($legacyLog['directories'])) {
            $this->error('Log de rollback está vazio ou corrompido.');

            return;
        }

        // Identificar e remover rotas de domínios criados
        foreach ($legacyLog['created'] ?? [] as $file) {
            // Verificar se é um arquivo de rotas de domínio
            if (str_contains($file, 'routes/domains/') && str_ends_with($file, '.php')) {
                $domainName = basename($file, '.php');
                $domainName = \Illuminate\Support\Str::studly(str_replace('-', '', $domainName));

                // Remover rotas do domínio usando RouteManager
                if ($this->routeManager->removeDomainRoutes($domainName)) {
                    $this->info("  ✓ Rotas do domínio {$domainName} removidas");
                }
            }
        }

        // Restaurar arquivos modificados
        foreach ($legacyLog['modified'] ?? [] as $file) {
            if (File::exists($file['backup'])) {
                File::copy($file['backup'], $file['file']);
                $this->info("  ✓ Arquivo restaurado: {$file['file']}");
            }
        }
        // Remover arquivos criados
        foreach ($legacyLog['created'] ?? [] as $file) {
            if (File::exists($file)) {
                File::delete($file);
                $this->info("  ✓ Arquivo removido: $file");
            }
        }
        // Remover diretórios criados (em ordem reversa para garantir remoção)
        foreach (array_reverse($legacyLog['directories'] ?? []) as $dir) {
            if (is_dir($dir) && count(scandir($dir)) === 2) { // vazio
                rmdir($dir);
                $this->info("  ✓ Diretório removido: $dir");
            }
        }
        // Limpar log
        $this->rollbackLogger->clearLog();
        $this->info('✅ Rollback concluído!');
    }

    /**
     * Registra um arquivo criado para possível rollback
     */
    private function logCreatedFile(string $file): void
    {
        $this->rollbackLogger->logCreatedFile($file, $this->currentSessionId);
    }

    /**
     * Registra um arquivo modificado para possível rollback (salva backup)
     */
    private function logModifiedFile(string $file): void
    {
        $this->rollbackLogger->logModifiedFile($file, $this->currentSessionId);
    }

    /**
     * Registra um diretório criado para possível rollback
     */
    private function logCreatedDirectory(string $dir): void
    {
        $this->rollbackLogger->logCreatedDirectory($dir, $this->currentSessionId);
    }

    /**
     * Gera um CRUD adicional dentro do domínio
     */
    private function generateAdditionalCrud(array $crudConfig): void
    {
        // Preparar configuração para o CRUD adicional
        $additionalConfig = [
            'domain' => $this->config['domain'], // Usa o mesmo domínio
            'model' => $crudConfig['model'],
            'schema' => $crudConfig['schema'] ?? '',
            'foreignKeys' => $crudConfig['foreignKeys'] ?? [],
            'generateTests' => $this->config['generateTests'] ?? false,
        ];

        // Gerar Model
        $modelGenerator = app(ModelGenerator::class);
        $modelPath = app_path("Domains/{$additionalConfig['domain']}/Models/{$additionalConfig['model']}.php");
        if ($modelGenerator->generate($additionalConfig)) {
            $this->info("    ✓ Model {$additionalConfig['model']} gerada");
            if (file_exists($modelPath)) {
                $this->logCreatedFile($modelPath);
            }
        }

        // Processar relações bidirecionais entre modelos (se configuração completa)
        if (! empty($additionalConfig['foreignKeys'])) {
            $hasCompleteStructure = true;
            foreach ($additionalConfig['foreignKeys'] as $fk) {
                if (! isset($fk['relation']) || ! isset($fk['model']) || ! isset($fk['domain'])) {
                    $hasCompleteStructure = false;
                    break;
                }
            }

            if ($hasCompleteStructure) {
                $this->modelRelationsManager->createRelationships(
                    $additionalConfig['foreignKeys'],
                    $additionalConfig['domain'],
                    $additionalConfig['model']
                );
                $this->info("    ✓ Relações configuradas para {$additionalConfig['model']}");
            }
        }

        // Gerar Migration
        $migrationGenerator = app(MigrationGenerator::class);
        $migrationDir = app_path("Domains/{$additionalConfig['domain']}/Migrations");
        $tableName = \Illuminate\Support\Str::snake(\Illuminate\Support\Str::plural($additionalConfig['model']));
        $migrationFile = date('Y_m_d_His') . "_create_{$tableName}_table.php";
        $migrationPath = $migrationDir . '/' . $migrationFile;

        if ($migrationGenerator->generate($additionalConfig)) {
            $this->info("    ✓ Migration para {$additionalConfig['model']} gerada");
            if (file_exists($migrationPath)) {
                $this->logCreatedFile($migrationPath);
            }
        }

        // Gerar Service
        $serviceGenerator = app(ServiceGenerator::class);
        $servicePath = app_path("Domains/{$additionalConfig['domain']}/Services/{$additionalConfig['model']}Service.php");
        if ($serviceGenerator->generate($additionalConfig)) {
            $this->info("    ✓ Service para {$additionalConfig['model']} gerado");
            if (file_exists($servicePath)) {
                $this->logCreatedFile($servicePath);
            }
        }

        // Gerar Controller
        $controllerGenerator = app(ControllerGenerator::class);
        $controllerPath = app_path("Domains/{$additionalConfig['domain']}/Controllers/{$additionalConfig['model']}Controller.php");
        if ($controllerGenerator->generate($additionalConfig)) {
            $this->info("    ✓ Controller para {$additionalConfig['model']} gerado");
            if (file_exists($controllerPath)) {
                $this->logCreatedFile($controllerPath);
            }
        }

        // Atualizar rotas
        $this->routeManager->addRoutes($additionalConfig);
        $this->info("    ✓ Rotas para {$additionalConfig['model']} adicionadas");

        // Gerar Seeder
        $seederGenerator = app(SeederGenerator::class);
        $seederPath = app_path("Domains/{$additionalConfig['domain']}/Seeders/{$additionalConfig['model']}Seeder.php");
        if ($seederGenerator->generate($additionalConfig)) {
            $this->info("    ✓ Seeder para {$additionalConfig['model']} gerado");
            if (file_exists($seederPath)) {
                $this->logCreatedFile($seederPath);
            }
        }

        // Adicionar abilities para o modelo adicional
        $this->abilityManager->addAbility($additionalConfig['domain'], $additionalConfig['model']);
        $this->info("    ✓ Abilities para {$additionalConfig['model']} adicionadas");

        // Gerar frontend para o CRUD adicional
        if (! $this->option('skip-frontend')) {
            $this->generateFrontendForAdditionalCrud($additionalConfig);
        }
    }

    /**
     * Salva o log de rollback em disco
     */
    private function saveRollbackLog(): void
    {
        $dir = dirname($this->rollbackLogPath);
        if (! file_exists($dir)) {
            mkdir($dir, 0755, true);
        }
        file_put_contents($this->rollbackLogPath, json_encode($this->rollbackLog, JSON_PRETTY_PRINT));
    }

    /**
     * Gera o frontend para um CRUD adicional
     */
    private function generateFrontendForAdditionalCrud(array $config): void
    {
        $this->info("    🎨 Gerando frontend para {$config['model']}...");

        // Gerar TypeScript types
        $typesGenerator = app(TypesGenerator::class);
        if ($typesGenerator->generate($config)) {
            $this->info('      ✓ Types TypeScript geradas');

            // Registrar arquivo gerado para rollback
            $typesPath = $this->getFrontendPath() . '/pages/' . Str::kebab($config['domain']) . "/types/{$config['model']}.ts";
            if (file_exists($typesPath)) {
                $this->logCreatedFile($typesPath);
            }
        }

        // Gerar Store Pinia
        $storeGenerator = app(StoreGenerator::class);
        if ($storeGenerator->generate($config)) {
            $this->info('      ✓ Store Pinia gerada');

            // Registrar arquivo gerado para rollback
            $storePath = $this->getFrontendPath() . '/pages/' . Str::kebab($config['domain']) . '/stores/' . Str::camel($config['model']) . 'Store.ts';
            if (file_exists($storePath)) {
                $this->logCreatedFile($storePath);
            }
        }

        // Gerar Service
        $frontendServiceGenerator = app(FrontEndServiceGenerator::class);
        if ($frontendServiceGenerator->generate($config)) {
            $this->info('      ✓ Service do frontend gerado');

            // Registrar arquivo gerado para rollback
            $servicePath = $this->getFrontendPath() . '/pages/' . Str::kebab($config['domain']) . '/services/' . Str::camel($config['model']) . 'Service.ts';
            if (file_exists($servicePath)) {
                $this->logCreatedFile($servicePath);
            }
        }

        // Gerar Form Component
        $formGenerator = app(FormGenerator::class);

        // Adicionar configuração de anexo ao formulário (sempre false para CRUDs adicionais por padrão)
        $config['shouldAttach'] = $config['attachToForm'] ?? false;

        if ($formGenerator->generate($config)) {
            $this->info('      ✓ Componente Form gerado');

            // Registrar arquivo gerado para rollback
            $formPath = $this->getFrontendPath() . '/pages/' . Str::kebab($config['domain']) . "/components/{$config['model']}Form.vue";
            if (file_exists($formPath)) {
                $this->logCreatedFile($formPath);
            }
        }

        // Gerar List Component
        $listGenerator = app(ListGenerator::class);
        if ($listGenerator->generate($config)) {
            $this->info('      ✓ Componente List gerado');

            // Registrar arquivo gerado para rollback
            $listPath = $this->getFrontendPath() . '/pages/' . Str::kebab($config['domain']) . "/components/{$config['model']}List.vue";
            if (file_exists($listPath)) {
                $this->logCreatedFile($listPath);
            }
        }

        // Gerar Criar Component
        $criarGenerator = app(CriarGenerator::class);
        if ($criarGenerator->generate($config)) {
            $this->info('      ✓ Componente Criar gerado');

            // Registrar arquivo gerado para rollback
            $criarPath = $this->getFrontendPath() . '/pages/' . Str::kebab($config['domain']) . "/components/Criar{$config['model']}.vue";
            if (file_exists($criarPath)) {
                $this->logCreatedFile($criarPath);
            }
        }

        // Gerar Editar Component
        $editarGenerator = app(EditarGenerator::class);
        if ($editarGenerator->generate($config)) {
            $this->info('      ✓ Componente Editar gerado');

            // Registrar arquivo gerado para rollback
            $editarPath = $this->getFrontendPath() . '/pages/' . Str::kebab($config['domain']) . "/components/Editar{$config['model']}.vue";
            if (file_exists($editarPath)) {
                $this->logCreatedFile($editarPath);
            }
        }
    }
}
