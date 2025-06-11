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
use App\Domains\Library\Seeders\BookSeeder;
use App\Domains\Library\Seeders\BookLoanSeeder;
use App\Domains\TestMultiple\Seeders\ParentSeeder;
use App\Domains\BlogSystem\Seeders\PostSeeder;
use App\Domains\TestFinal\Seeders\MainModelSeeder;
use App\Domains\TestFinal\Seeders\SubModelSeeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesPermissionSeeder::class);
        $this->call(AuthDomainDatabaseSeeder::class);
    }
}
