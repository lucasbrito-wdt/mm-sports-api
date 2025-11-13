<?php

namespace App\Console\Commands\Generator\Generators\BackEnd;

use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;

class SeederGenerator
{
    private TemplateManager $templateManager;

    public function __construct(TemplateManager $templateManager)
    {
        $this->templateManager = $templateManager;
    }

    /**
     * Gera um seeder para o modelo especificado
     *
     * @param array $config Configuração do seeder
     * @return bool Resultado da operação
     */
    public function generate(array $config): bool
    {
        $modelName = $config['model'] ?? $config['baseModel'] ?? null;
        if (!$modelName) {
            throw new \InvalidArgumentException('Configuração do SeederGenerator requer a chave "model" ou "baseModel".');
        }
        $domain = $config['domain'];
        $foreignKeys = $config['foreignKeys'] ?? [];
        $seederName = $modelName . 'Seeder';

        // Gerar conteúdo do seeder
        $seederContent = $this->generateSeederContent($domain, $seederName, $modelName, $foreignKeys);

        // Criar diretório se não existir
        $seederDir = app_path("Domains\\{$domain}\\Seeders");
        if (!File::exists($seederDir)) {
            File::makeDirectory($seederDir, 0755, true);
        }

        // Salvar o arquivo
        $seederPath = "{$seederDir}/{$seederName}.php";
        File::put($seederPath, $seederContent);

        // Registrar o seeder no DatabaseSeeder principal
        $this->registerSeederInDatabaseSeeder($domain, $seederName);

        return true;
    }

    /**
     * Gera o conteúdo do seeder
     *
     * @param string $domain Nome do domínio
     * @param string $seederName Nome do seeder
     * @param string $modelName Nome do modelo
     * @param array $foreignKeys Chaves estrangeiras
     * @return string Conteúdo do seeder
     */
    private function generateSeederContent(string $domain, string $seederName, string $modelName, array $foreignKeys = []): string
    {
        // Gerar imports de modelos relacionados se houver chaves estrangeiras
        $additionalImports = '';
        $additionalSeeders = '';

        if (!empty($foreignKeys)) {
            foreach ($foreignKeys as $fk) {
                $fkDomain = $fk['domain'] ?? $domain; // Usar o mesmo domínio se não especificado
                $fkModel = $fk['model'];
                $additionalImports .= "use App\\Domains\\{$fkDomain}\\Models\\{$fkModel};\n";

                // Para relacionamentos belongsTo, pode ser necessário criar registros relacionados primeiro
                if ($fk['relation'] === 'belongsTo' || $fk['relation'] === 'hasMany' || $fk['relation'] === 'hasOne') {
                    $additionalSeeders .= "        // Garantir que existam {$fkModel}s para o relacionamento\n";
                    $additionalSeeders .= "        // \$this->call(\\App\\Domains\\{$fkDomain}\\Seeders\\{$fkModel}Seeder::class);\n\n";
                }
            }
        }

        // Tentar usar o stub template se disponível, senão usar template básico
        try {
            $seederContent = $this->templateManager->processStub(
                'BackEnd/seeder.stub',
                [
                    '{{namespace}}' => "App\\Domains\\{$domain}\\Seeders",
                    '{{seederName}}' => $seederName,
                    '{{domainName}}' => $domain,
                    '{{modelName}}' => $modelName,
                    '{{additionalImports}}' => $additionalImports,
                    '{{additionalSeeders}}' => $additionalSeeders
                ]
            );
        } catch (\Exception $e) {
            // Se o stub não existir, usar template básico
            $seederContent = $this->generateBasicSeederTemplate($domain, $seederName, $modelName, $additionalImports, $additionalSeeders);
        }

        return $seederContent;
    }

    /**
     * Gera template básico do seeder quando o stub não está disponível
     *
     * @param string $domain Nome do domínio
     * @param string $seederName Nome do seeder
     * @param string $modelName Nome do modelo
     * @param string $additionalImports Imports adicionais
     * @param string $additionalSeeders Seeders adicionais
     * @return string Conteúdo do seeder
     */
    private function generateBasicSeederTemplate(string $domain, string $seederName, string $modelName, string $additionalImports = '', string $additionalSeeders = ''): string
    {
        return "<?php

namespace App\\Domains\\{$domain}\\Seeders;

use App\\Domains\\{$domain}\\Models\\{$modelName};
{$additionalImports}use Illuminate\\Database\\Seeder;

class {$seederName} extends Seeder
{
    /**
     * Run the database seeds for {$modelName}.
     *
     * @return void
     */
    public function run(): void
    {
{$additionalSeeders}        // Criar registros de exemplo para {$modelName}
        {$modelName}::factory(10)->create();

        // Ou criar registros manualmente:
        // {$modelName}::create([
        //     'campo1' => 'valor1',
        //     'campo2' => 'valor2',
        // ]);
    }
}
";
    }

    /**
     * Registra o seeder no DatabaseSeeder principal
     *
     * @param string $domain Nome do domínio
     * @param string $seederName Nome do seeder
     * @return void
     */
    private function registerSeederInDatabaseSeeder(string $domain, string $seederName): void
    {
        $databaseSeederPath = database_path('seeders/DatabaseSeeder.php');

        if (!File::exists($databaseSeederPath)) {
            return;
        }

        $content = File::get($databaseSeederPath);
        $seederClass = "App\\Domains\\{$domain}\\Seeders\\{$seederName}";

        // Verificar se o seeder já está registrado (verifica use statement e chamada)
        if (
            strpos($content, "use {$seederClass};") !== false ||
            strpos($content, "{$seederName}::class") !== false
        ) {
            return;
        }

        // Dividir o conteúdo em linhas para facilitar a manipulação
        $lines = explode("\n", $content);
        $newLines = [];
        $useAdded = false;
        $callAdded = false;

        foreach ($lines as $lineNumber => $line) {
            $newLines[] = $line;

            // Adicionar use statement após o último use existente
            if (!$useAdded && preg_match('/^use\s+.*;\s*$/', trim($line))) {
                // Verifica se é a última linha de use olhando a próxima linha
                $nextLineIndex = $lineNumber + 1;
                if (
                    !isset($lines[$nextLineIndex]) ||
                    !preg_match('/^use\s+.*;\s*$/', trim($lines[$nextLineIndex]))
                ) {
                    $newLines[] = "use {$seederClass};";
                    $useAdded = true;
                }
            }

            // Adicionar chamada no método run após a última chamada existente
            if (!$callAdded && preg_match('/\$this->call\(.*\);/', trim($line))) {
                // Verifica se é a última linha de call olhando a próxima linha
                $nextLineIndex = $lineNumber + 1;
                if (
                    !isset($lines[$nextLineIndex]) ||
                    !preg_match('/\$this->call\(.*\);/', trim($lines[$nextLineIndex]))
                ) {
                    $newLines[] = "        \$this->call({$seederName}::class);";
                    $callAdded = true;
                }
            }
        }

        // Se não conseguiu adicionar o use statement (não havia outros uses), adiciona após a declaração da classe
        if (!$useAdded) {
            $newContent = [];
            foreach ($newLines as $line) {
                $newContent[] = $line;
                if (preg_match('/^use Illuminate\\Database\\Seeder;\s*$/', trim($line))) {
                    $newContent[] = "use {$seederClass};";
                }
            }
            $newLines = $newContent;
        }

        // Se não conseguiu adicionar a chamada (não havia outras calls), adiciona no início do método run
        if (!$callAdded) {
            $newContent = [];
            foreach ($newLines as $line) {
                $newContent[] = $line;
                if (preg_match('/public function run\(\): void\s*$/', trim($line))) {
                    // Procura a próxima linha que contém '{'
                    $braceFound = false;
                    for ($i = count($newContent); $i < count($newLines); $i++) {
                        if (isset($newLines[$i]) && strpos($newLines[$i], '{') !== false) {
                            $newContent[] = $newLines[$i];
                            $newContent[] = "        \$this->call({$seederName}::class);";
                            $braceFound = true;
                            break;
                        }
                    }
                    if ($braceFound) {
                        // Pula a linha da chave que já foi adicionada
                        array_splice($newLines, 0, count($newContent));
                        break;
                    }
                }
            }
            $newLines = array_merge($newContent, $newLines);
        }

        // Reconstroir o conteúdo e salvar
        $newContent = implode("\n", $newLines);
        File::put($databaseSeederPath, $newContent);
    }
}
