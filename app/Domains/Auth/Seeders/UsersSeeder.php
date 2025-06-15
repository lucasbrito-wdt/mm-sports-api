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

            // 20 usuários adicionais
            [
                'data' => [
                    'name' => 'João Silva',
                    'email' => 'joao.silva@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Maria Santos',
                    'email' => 'maria.santos@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Pedro Oliveira',
                    'email' => 'pedro.oliveira@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Ana Costa',
                    'email' => 'ana.costa@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Carlos Pereira',
                    'email' => 'carlos.pereira@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Fernanda Lima',
                    'email' => 'fernanda.lima@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Roberto Alves',
                    'email' => 'roberto.alves@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Juliana Ferreira',
                    'email' => 'juliana.ferreira@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Lucas Ribeiro',
                    'email' => 'lucas.ribeiro@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Mariana Gomes',
                    'email' => 'mariana.gomes@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Rafael Castro',
                    'email' => 'rafael.castro@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Patrícia Martins',
                    'email' => 'patricia.martins@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Gustavo Rocha',
                    'email' => 'gustavo.rocha@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Camila Cardoso',
                    'email' => 'camila.cardoso@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Fernando Souza',
                    'email' => 'fernando.souza@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Carolina Almeida',
                    'email' => 'carolina.almeida@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Rodrigo Nunes',
                    'email' => 'rodrigo.nunes@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Bianca Moreira',
                    'email' => 'bianca.moreira@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Paulo Barbosa',
                    'email' => 'paulo.barbosa@exemplo.com',
                    'password' => Hash::make('123456'),
                    'email_verified_at' => now(),
                ],
                'roleName' => 'user',
            ],
            [
                'data' => [
                    'name' => 'Amanda Pinto',
                    'email' => 'amanda.pinto@exemplo.com',
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
