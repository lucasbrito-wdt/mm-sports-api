<?php

namespace App\Domains\BlogComplete\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Domains\BlogComplete\Models\Category;


class CategorySeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds for Category.
     *
     * @return void
     */
    public function run(): void
    {
        // Para usar factories, crie o arquivo de factory correspondente:
        // Category::factory(10)->create();

        // Criar registros manualmente de exemplo:
        /*
        Category::create([
            'nome' => 'Exemplo de Category',
            // Adicione mais campos conforme necessário
        ]);
        */

        // Exemplo com relacionamentos:
        /*
        $relatedModel = RelatedModel::first();
        if ($relatedModel) {
            Category::create([
                'nome' => 'Exemplo com relação',
                'related_model_id' => $relatedModel->id,
                // Outros campos...
            ]);
        }
        */
    }
}
