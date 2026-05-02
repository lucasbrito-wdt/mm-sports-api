<?php

namespace Database\Seeders;

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Seeders\AuthDomainDatabaseSeeder;
use App\Domains\Catalog\Seeders\AttributeSeeder;
use App\Domains\Catalog\Seeders\AttributeValueSeeder;
use App\Domains\Catalog\Seeders\CategorySeeder;
use App\Domains\Catalog\Seeders\ProductDemoSeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesPermissionSeeder::class);
        $this->call(AuthDomainDatabaseSeeder::class);
        $this->call(AttributeSeeder::class);
        $this->call(AttributeValueSeeder::class);
        #$this->call(CategorySeeder::class);
        #$this->call(ProductDemoSeeder::class);
    }
}
