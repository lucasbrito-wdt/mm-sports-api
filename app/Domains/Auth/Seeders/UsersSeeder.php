<?php

namespace App\Domains\Auth\Seeders;

use App\Domains\Auth\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UsersSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $users = [
            [
                'data' => [
                    'name' => 'Admin',
                    'email' => 'admin@admin.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'admin',
            ],
            [
                'data' => [
                    'name' => 'Usuário',
                    'email' => 'user@codifytech.com.br',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
        ];

        foreach ($users as $user) {
            $adminUser = User::create($user['data']);
            $adminUser->assignRole($user['roleName']);
        }
    }
}
