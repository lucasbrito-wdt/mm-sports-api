<?php

namespace App\Console\Commands\Generator\Generators\FrontEnd;

use App\Console\Commands\Generator\Generators\FrontEnd\ZodSchemaGenerator;
use App\Console\Commands\Generator\Utils\TemplateManager;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class SchemaGenerator
{
    use FrontendPathTrait;

    private TemplateManager $templateManager;
    private ZodSchemaGenerator $zodSchemaGenerator;

    public function __construct(TemplateManager $templateManager, ZodSchemaGenerator $zodSchemaGenerator)
    {
        $this->templateManager = $templateManager;
        $this->zodSchemaGenerator = $zodSchemaGenerator;
    }

    public function generate(array $config): bool
    {
        $modelName = $config['model'];
        $frontEndAbsoluteDir = $this->getFrontendPath();

        $fullPath = sprintf(
            '%s/%s',
            $frontEndAbsoluteDir,
            'schemas'
        );

        // Criar diretório se não existir
        if (!File::exists($fullPath)) {
            File::makeDirectory($fullPath, 0755, true);
        }

        // Nome do arquivo: {entity}.ts (ex: user.ts)
        $fileName = Str::kebab($modelName) . '.ts';
        $filePath = $fullPath . '/' . $fileName;

        // Verificar se o arquivo já existe
        if (File::exists($filePath) && !($config['force'] ?? false)) {
            return false;
        }

        // Gerar schemas
        $createSchema = $this->zodSchemaGenerator->generateCreateSchema($config);
        $updateSchema = $this->zodSchemaGenerator->generateUpdateSchema($config);

        // Construir conteúdo do arquivo
        $schemaContent = $this->buildSchemaFile($modelName, $createSchema, $updateSchema);

        // Salvar o arquivo
        File::put($filePath, $schemaContent);

        return true;
    }

    private function buildSchemaFile(string $modelName, string $createSchema, string $updateSchema): string
    {
        $entityCamel = Str::camel($modelName);
        $entityPascal = Str::studly($modelName);

        return <<<TYPESCRIPT
import { z } from 'zod'

export const {$entityCamel}CreateSchema = {$createSchema}

export const {$entityCamel}UpdateSchema = {$updateSchema}

export type {$entityPascal}CreateInput = z.infer<typeof {$entityCamel}CreateSchema>
export type {$entityPascal}UpdateInput = z.infer<typeof {$entityCamel}UpdateSchema>

TYPESCRIPT;
    }
}
