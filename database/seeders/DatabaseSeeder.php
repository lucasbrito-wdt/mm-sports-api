<?php

namespace Database\Seeders;

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Seeders\AuthDomainDatabaseSeeder;
use Illuminate\Database\Seeder;
use App\Domains\TesteSegundo\Seeders\TesteSeeder;
use App\Domains\Produto\Seeders\ProdutoSeeder;
use App\Domains\Produto\Seeders\TesteFrontendSeeder;
use App\Domains\TesteDomain\Seeders\TesteModelSeeder;
use App\Domains\Produto\Seeders\TesteCompletoSeeder;
use App\Domains\Catalog\Seeders\CategorySeeder;
use App\Domains\Catalog\Seeders\ProductSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesPermissionSeeder::class);
        $this->call(AuthDomainDatabaseSeeder::class);
        $this->call(TesteSeeder::class);
        $this->call(ProdutoSeeder::class);
        $this->call(TesteFrontendSeeder::class);
        $this->call(TesteModelSeeder::class);
        $this->call(TesteCompletoSeeder::class);
        $this->call(CategorySeeder::class);
        $this->call(ProductSeeder::class);
    }
}
