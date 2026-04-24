<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Throwable;

class CreatePostgresDatabaseCommand extends Command
{
    protected $signature = 'db:create
                            {--connection= : Nome da conexão (padrão: pgsql)}';

    protected $description = 'Cria o banco PostgreSQL (UTF-8, ICU, pt-BR) se ainda não existir — mesmo padrão de docs/database/postgresql-setup.md';

    public function handle(): int
    {
        $name = (string) ($this->option('connection') ?: 'pgsql');
        $config = Config::get("database.connections.{$name}");

        if (($config['driver'] ?? null) !== 'pgsql') {
            $this->components->info('Conexão não é pgsql; nada a fazer.');

            return self::SUCCESS;
        }

        $targetDb = (string) ($config['database'] ?? '');
        if ($targetDb === '' || $targetDb === 'postgres' || $targetDb === 'template0' || $targetDb === 'template1') {
            $this->error('Defina DB_DATABASE no .env com o nome do banco da aplicação (não use postgres/template).');

            return self::FAILURE;
        }

        $bootstrap = $name.'_bootstrap';
        $bootstrapConfig = array_merge($config, [
            'database' => env('DB_BOOTSTRAP_DATABASE', 'postgres'),
            'username' => env('DB_ROOT_USER', $config['username'] ?? ''),
            'password' => env('DB_ROOT_PASSWORD', $config['password'] ?? ''),
        ]);

        Config::set("database.connections.{$bootstrap}", $bootstrapConfig);
        DB::purge($bootstrap);

        $conn = DB::connection($bootstrap);

        try {
            if ($this->databaseExists($conn, $targetDb)) {
                $this->components->info("Banco [{$targetDb}] já existe.");

                return self::SUCCESS;
            }
        } catch (Throwable $e) {
            $this->error('Falha ao verificar/criar o banco: '.$e->getMessage());
            $this->line('Dica: use DB_ROOT_USER / DB_ROOT_PASSWORD de um superusuário (ex.: postgres) com permissão CREATEDB.');

            return self::FAILURE;
        }

        $identifier = $this->quoteIdentifier($targetDb);
        $sql = <<<SQL
CREATE DATABASE {$identifier}
    ENCODING = 'UTF8'
    LOCALE_PROVIDER = icu
    ICU_LOCALE = 'pt-BR-u-ks-level1'
    LC_COLLATE = 'pt_BR.UTF-8'
    LC_CTYPE = 'pt_BR.UTF-8'
    TEMPLATE = template0
SQL;

        try {
            $conn->getPdo()->exec($sql);
        } catch (Throwable $e) {
            $this->error('CREATE DATABASE falhou: '.$e->getMessage());
            $this->line('Dica: verifique versão do PostgreSQL (ICU/LOCALE_provider costuma exigir 15+), locale pt_BR no SO e credenciais em DB_ROOT_*.');

            return self::FAILURE;
        }

        $this->components->info("Banco [{$targetDb}] criado com o padrão UTF-8 / ICU (pt-BR).");

        return self::SUCCESS;
    }

    private function databaseExists($connection, string $name): bool
    {
        $row = $connection->selectOne(
            'select 1 as v from pg_database where datname = ? limit 1',
            [$name]
        );

        return $row !== null;
    }

    private function quoteIdentifier(string $name): string
    {
        return '"'.str_replace('"', '""', $name).'"';
    }
}
