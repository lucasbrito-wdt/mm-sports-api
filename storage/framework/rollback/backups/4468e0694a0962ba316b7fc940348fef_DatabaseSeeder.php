<?php

namespace Database\Seeders;

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Seeders\AuthDomainDatabaseSeeder;
use Illuminate\Database\Seeder;
use App\Domains\Fornecedor\Seeders\FornecedorSeeder;
use App\Domains\Empresa\Seeders\EmpresaSeeder;
use App\Domains\Diretor\Seeders\DiretorSeeder;
use App\Domains\Produto\Seeders\ProdutoSeeder;
use App\Domains\Teste\Seeders\TesteSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesPermissionSeeder::class);
        $this->call(AuthDomainDatabaseSeeder::class);
        $this->call(FornecedorSeeder::class);
        $this->call(EmpresaSeeder::class);
        $this->call(DiretorSeeder::class);
        $this->call(ProdutoSeeder::class);
        $this->call(TesteSeeder::class);
    }
}
