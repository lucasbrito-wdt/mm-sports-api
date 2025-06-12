<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Domains\ACL\Seeders\RolesPermissionSeeder;
use App\Domains\Auth\Seeders\AuthDomainDatabaseSeeder;
use App\Domains\BlogComplete\Seeders\PostSeeder;
use App\Domains\BlogComplete\Seeders\CommentSeeder;
use App\Domains\BlogComplete\Seeders\TagSeeder;
use App\Domains\BlogComplete\Seeders\CategorySeeder;



class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(RolesPermissionSeeder::class);
        $this->call(AuthDomainDatabaseSeeder::class);
        $this->call(PostSeeder::class);
        $this->call(CommentSeeder::class);
        $this->call(TagSeeder::class);
        $this->call(CategorySeeder::class);
    }
}
