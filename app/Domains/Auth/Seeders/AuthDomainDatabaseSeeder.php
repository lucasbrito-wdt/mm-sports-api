<?php

namespace App\Domains\Auth\Seeders;

use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use Illuminate\Database\Seeder;

class AuthDomainDatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     *
     * @return void
     */
    public function run(): void
    {
        $this->call([
            RolesPermissionSeeder::class,
            UsersSeeder::class
        ]);
    }
}
