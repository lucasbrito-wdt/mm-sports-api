<?php

namespace App\Domains\Catalog\Seeders;

use App\Domains\Catalog\Models\Category;
use Illuminate\Database\Seeder;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Seleções',        'slug' => 'selecoes',        'display_order' => 1],
            ['name' => 'Times Nacionais', 'slug' => 'times-nacionais', 'display_order' => 2],
            ['name' => 'Times Europeus',  'slug' => 'times-europeus',  'display_order' => 3],
            ['name' => 'Bonés e Gorros',  'slug' => 'bones-e-gorros',  'display_order' => 4],
            ['name' => 'Acessórios',      'slug' => 'acessorios',      'display_order' => 5],
            ['name' => 'Chuteiras',       'slug' => 'chuteiras',       'display_order' => 6],
        ];

        foreach ($categories as $data) {
            Category::updateOrCreate(
                ['slug' => $data['slug']],
                [
                    'name'          => $data['name'],
                    'is_active'     => true,
                    'display_order' => $data['display_order'],
                ]
            );
        }
    }
}
