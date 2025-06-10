<?php

namespace App\Console\Commands\Generator\Utils;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ModelRelationsManager
{
    /**
     * Cria relacionamentos bidirecionais entre modelos.
     *
     * @param array $foreignKeys Array de chaves estrangeiras configuradas
     * @param string $domainName Nome do domínio atual
     * @param string $modelName Nome do modelo atual
     */
    public function createRelationships(array $foreignKeys, string $domainName, string $modelName): void
    {
        if (empty($foreignKeys)) {
            return;
        }

        foreach ($foreignKeys as $foreign) {
            // Determina os tipos de relacionamentos
            if ($foreign['relation'] === 'belongsTo') {
                // Se o modelo atual tem uma chave estrangeira (belongsTo),
                // o outro modelo tem um hasMany ou hasOne (geralmente hasMany)
                $inverseRelationType = 'hasMany';

                // Verifica se o modelo relacionado existe antes de tentar atualizá-lo
                $relatedModelPath = app_path("Domains/{$foreign['domain']}/Models/{$foreign['model']}.php");

                if (File::exists($relatedModelPath)) {
                    // Modelo atual: belongsTo -> Modelo relacionado: hasMany
                    // Adiciona hasMany no modelo relacionado
                    $this->updateModel(
                        $foreign['model'],  // Modelo relacionado
                        $modelName,         // Modelo atual
                        $foreign['domain'], // Domínio do modelo relacionado
                        $domainName,        // Domínio do modelo atual
                        $inverseRelationType // hasMany
                    );
                }

                // Adiciona belongsTo no modelo atual
                $this->updateModel(
                    $modelName,         // Modelo atual
                    $foreign['model'],  // Modelo relacionado
                    $domainName,        // Domínio do modelo atual
                    $foreign['domain'], // Domínio do modelo relacionado
                    'belongsTo'         // belongsTo
                );
            } else if ($foreign['relation'] === 'hasOne') {
                // Se o modelo atual tem uma relação hasOne,
                // o outro modelo tem um belongsTo

                // Verifica se o modelo relacionado existe antes de tentar atualizá-lo
                $relatedModelPath = app_path("Domains/{$foreign['domain']}/Models/{$foreign['model']}.php");

                if (File::exists($relatedModelPath)) {
                    // Modelo atual: hasOne -> Modelo relacionado: belongsTo
                    // Adiciona belongsTo no modelo relacionado
                    $this->updateModel(
                        $foreign['model'],  // Modelo relacionado
                        $modelName,         // Modelo atual
                        $foreign['domain'], // Domínio do modelo relacionado
                        $domainName,        // Domínio do modelo atual
                        'belongsTo'         // belongsTo
                    );
                }

                // Adiciona hasOne no modelo atual
                $this->updateModel(
                    $modelName,         // Modelo atual
                    $foreign['model'],  // Modelo relacionado
                    $domainName,        // Domínio do modelo atual
                    $foreign['domain'], // Domínio do modelo relacionado
                    'hasOne'            // hasOne
                );
            } else {
                // Outros tipos de relacionamentos (hasMany, etc)
                $inverseRelationType = 'belongsTo';

                // Verifica se o modelo relacionado existe antes de tentar atualizá-lo
                $relatedModelPath = app_path("Domains/{$foreign['domain']}/Models/{$foreign['model']}.php");

                if (File::exists($relatedModelPath)) {
                    // Adiciona o relacionamento inverso no modelo relacionado
                    $this->updateModel(
                        $foreign['model'],  // Modelo relacionado
                        $modelName,         // Modelo atual
                        $foreign['domain'], // Domínio do modelo relacionado
                        $domainName,        // Domínio do modelo atual
                        $inverseRelationType // belongsTo
                    );
                }

                // Adiciona o relacionamento definido no modelo atual
                $this->updateModel(
                    $modelName,         // Modelo atual
                    $foreign['model'],  // Modelo relacionado
                    $domainName,        // Domínio do modelo atual
                    $foreign['domain'], // Domínio do modelo relacionado
                    $foreign['relation'] // Tipo de relacionamento original
                );
            }
        }
    }

    protected function updateModel(string $modelName, string $modelRelation, string $domainName, string $domainRelation, string $relationship): void
    {
        $modelPath = app_path("Domains/{$domainName}/Models/{$modelName}.php");

        if (!File::exists($modelPath)) {
            // Modelo não existe, não podemos adicionar a relação
            return;
        }

        $content = File::get($modelPath);
        $content = Str::substr($content, 0, -2); // Remove a última chave '}'

        // Verifica se já existe o import e o método de relacionamento
        [$importAdded, $importRelationsUsing, $checkTo, $class] = $this->getFileContentProperties($modelPath, $modelName, $domainName, $relationship, $modelRelation);

        // Só adiciona o import se não existir
        $modelContent = $this->updateModelContent($content, $modelRelation, $domainRelation, $importAdded, $importRelationsUsing, $relationship, $class);

        $methodName = $relationship == 'hasMany' ? Str::camel(Str::plural($modelRelation)) : Str::camel($modelRelation);

        // Só adiciona o método se não existir
        $modelContent = $this->addRelationMethod($modelContent, $checkTo, $modelRelation, $relationship, $methodName);

        // Só salva se houve alteração
        if ($modelContent !== $content . "}") {
            File::put($modelPath, $modelContent);
        }
    }

    protected function getFileContentProperties(string $modelPath, string $modelName, string $domainRelation, string $relationship, string $relatedModel): array
    {
        $content = File::get($modelPath);
        $importRelationsUsing = false;
        $importAdd = false;
        $checkTo = false;
        $class = '';

        // Padrões mais específicos para verificar imports existentes
        $modelImportPattern = '/use\s+App\\\\Domains\\\\' . preg_quote($domainRelation, '/') . '\\\\Models\\\\' . preg_quote($relatedModel, '/') . '\s*;/';

        // Mapear relacionamentos para os nomes corretos das classes do Eloquent
        $relationshipClassMap = [
            'belongsTo' => 'BelongsTo',
            'hasMany' => 'HasMany',
            'hasOne' => 'HasOne',
            'hasManyThrough' => 'HasManyThrough',
            'belongsToMany' => 'BelongsToMany'
        ];

        $relationshipClass = $relationshipClassMap[$relationship] ?? ucfirst($relationship);
        $relationImportPattern = '/use\s+Illuminate\\\\Database\\\\Eloquent\\\\Relations\\\\' . preg_quote($relationshipClass, '/') . '\s*;/';

        // Verificar se os imports já existem
        if (preg_match($modelImportPattern, $content)) {
            $importAdd = true;
        }

        if (preg_match($relationImportPattern, $content)) {
            $importRelationsUsing = true;
        }

        // Verificar se o método de relacionamento já existe
        $methodNameCheck = $relationship == 'hasMany' ? Str::camel(Str::plural($relatedModel)) : Str::camel($relatedModel);

        // Padrões mais flexíveis para detectar métodos existentes
        $methodPatterns = [
            '/public\s+function\s+' . preg_quote($methodNameCheck, '/') . '\s*\(\s*\)\s*:\s*' . preg_quote($relationshipClass, '/') . '/',
            '/public\s+function\s+' . preg_quote($methodNameCheck, '/') . '\s*\(\s*\)\s*:\s*' . preg_quote(strtolower($relationshipClass), '/') . '/',
            '/public\s+function\s+' . preg_quote($methodNameCheck, '/') . '\s*\(\s*\)/',
        ];

        foreach ($methodPatterns as $pattern) {
            if (preg_match($pattern, $content)) {
                $checkTo = true;
                break;
            }
        }

        // Extrair a linha da classe
        if (preg_match('/class\s+\w+\s+extends\s+\w+/', $content, $matches)) {
            $class = $matches[0];
        }

        return [$importAdd, $importRelationsUsing, $checkTo, $class];
    }

    /**
     * Atualiza o conteúdo do modelo adicionando os imports necessários
     *
     * @param string $modelContent Conteúdo atual do arquivo do modelo
     * @param string $modelRelation Nome do modelo relacionado
     * @param string $domainRelation Nome do domínio do modelo relacionado
     * @param bool $importAdded Se o import da classe do modelo já existe
     * @param bool $importRelationsUsing Se o import da classe de relacionamento já existe
     * @param string $relationship Tipo de relacionamento (hasMany, hasOne, belongsTo)
     * @param string $class Linha da definição da classe
     * @return string Conteúdo modificado do arquivo
     */
    protected function updateModelContent(string $modelContent, string $modelRelation, string $domainRelation, bool $importAdded, bool $importRelationsUsing, string $relationship, string $class): string
    {
        // Se ambos os imports já existem, não precisa fazer nada
        if ($importAdded && $importRelationsUsing) {
            return $modelContent;
        }

        $useStatements = [];
        $namespacePosition = -1;
        $lastUsePosition = -1;
        $classPosition = -1;

        // Procura a posição do namespace, último import e classe
        $lines = explode("\n", $modelContent);
        foreach ($lines as $index => $line) {
            $trimmedLine = trim($line);
            if (str_starts_with($trimmedLine, 'namespace ')) {
                $namespacePosition = $index;
            }
            if (str_starts_with($trimmedLine, 'use ')) {
                $lastUsePosition = $index;
            }
            if (str_contains($trimmedLine, 'class ') && str_contains($trimmedLine, 'extends')) {
                $classPosition = $index;
                break; // Uma vez que encontramos a classe, podemos parar de procurar
            }
        }

        // Mapear relacionamentos para os nomes corretos das classes do Eloquent
        $relationshipClassMap = [
            'belongsTo' => 'BelongsTo',
            'hasMany' => 'HasMany',
            'hasOne' => 'HasOne',
            'hasManyThrough' => 'HasManyThrough',
            'belongsToMany' => 'BelongsToMany'
        ];

        $relationshipClass = $relationshipClassMap[$relationship] ?? ucfirst($relationship);

        if (!$importAdded) {
            $useStatements[] = "use App\\Domains\\{$domainRelation}\\Models\\{$modelRelation};";
        }

        if (!$importRelationsUsing) {
            $useStatements[] = "use Illuminate\\Database\\Eloquent\\Relations\\{$relationshipClass};";
        }

        if (empty($useStatements)) {
            return $modelContent;
        }

        // Se encontramos imports, adicionamos abaixo do último
        if ($lastUsePosition >= 0) {
            $beforeImports = array_slice($lines, 0, $lastUsePosition + 1);
            $afterImports = array_slice($lines, $lastUsePosition + 1);

            return implode("\n", $beforeImports) . "\n" . implode("\n", $useStatements) . implode("\n", $afterImports);
        }

        // Se não encontramos imports mas encontramos o namespace, adicionamos depois do namespace
        if ($namespacePosition >= 0) {
            $beforeNamespace = array_slice($lines, 0, $namespacePosition + 1);
            $afterNamespace = array_slice($lines, $namespacePosition + 1);

            return implode("\n", $beforeNamespace) . "\n\n" . implode("\n", $useStatements) . "\n" . implode("\n", $afterNamespace);
        }

        // Se não encontramos nem namespace nem imports, adicionamos antes da classe
        if ($classPosition >= 0) {
            $beforeClass = array_slice($lines, 0, $classPosition);
            $afterClass = array_slice($lines, $classPosition);

            return implode("\n", $beforeClass) . implode("\n", $useStatements) . "\n\n" . implode("\n", $afterClass);
        }

        // Último recurso: adicionar no início do arquivo
        return implode("\n", $useStatements) . "\n\n" . $modelContent;
    }

    protected function addRelationMethod(string $modelContent, bool $relationChecked, string $relatedModel, string $relationMethod, string $methodName): string
    {
        if ($relationChecked) {
            // O método já existe, apenas devolvemos o conteúdo com a chave fechando
            return $modelContent . "\n}";
        }

        // Mapear relacionamentos para os nomes corretos das classes do Eloquent
        $relationshipClassMap = [
            'belongsTo' => 'BelongsTo',
            'hasMany' => 'HasMany',
            'hasOne' => 'HasOne',
            'hasManyThrough' => 'HasManyThrough',
            'belongsToMany' => 'BelongsToMany'
        ];

        $relationshipClass = $relationshipClassMap[$relationMethod] ?? ucfirst($relationMethod);

        // Extrai o nome do modelo atual a partir do conteúdo do arquivo
        $pattern = '/class\s+(\w+)\s+extends/';
        preg_match($pattern, $modelContent, $matches);

        if ($relationMethod === 'belongsTo') {
            // Para relações belongsTo, a chave estrangeira é o modelo relacionado em snake_case + _id
            $template = "\n    /**\n     * Get the {$relatedModel} that owns this record.\n     *\n     * @return \\Illuminate\\Database\\Eloquent\\Relations\\{$relationshipClass}\n     */\n    public function %s(): %s\n    {\n        return \$this->%s(%s::class);\n    }\n";
            $relation = sprintf($template, $methodName, $relationshipClass, $relationMethod, $relatedModel);
        } else if ($relationMethod === 'hasMany') {
            // Para relações hasMany, a chave estrangeira é o modelo atual em snake_case + _id
            $template = "\n    /**\n     * Get the %s for this record.\n     *\n     * @return \\Illuminate\\Database\\Eloquent\\Relations\\{$relationshipClass}\n     */\n    public function %s(): %s\n    {\n        return \$this->%s(%s::class);\n    }\n";
            $relation = sprintf(
                $template,
                Str::plural(Str::lower($relatedModel)),
                $methodName,
                $relationshipClass,
                $relationMethod,
                $relatedModel
            );
        } else if ($relationMethod === 'hasOne') {
            // Para relações hasOne, a chave estrangeira é o modelo atual em snake_case + _id
            $template = "\n    /**\n     * Get the %s associated with this record.\n     *\n     * @return \\Illuminate\\Database\\Eloquent\\Relations\\{$relationshipClass}\n     */\n    public function %s(): %s\n    {\n        return \$this->%s(%s::class);\n    }\n";
            $relation = sprintf(
                $template,
                Str::lower($relatedModel),
                $methodName,
                $relationshipClass,
                $relationMethod,
                $relatedModel
            );
        } else {
            // Outros tipos de relacionamentos
            $template = "\n    /**\n     * Relationship with {$relatedModel}.\n     *\n     * @return \\Illuminate\\Database\\Eloquent\\Relations\\{$relationshipClass}\n     */\n    public function %s(): %s\n    {\n        return \$this->%s(%s::class);\n    }\n";
            $relation = sprintf(
                $template,
                $methodName,
                $relationshipClass,
                $relationMethod,
                $relatedModel
            );
        }

        // Adiciona o relacionamento e fecha a classe
        return $modelContent . $relation . "}";
    }
}
