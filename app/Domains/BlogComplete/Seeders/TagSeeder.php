<?php

namespace App\Domains\BlogComplete\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Domains\BlogComplete\Models\Tag;


class TagSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds for Tag.
     *
     * @return void
     */
    public function run(): void
    {
        // Para usar factories, crie o arquivo de factory correspondente:
        // Tag::factory(10)->create();

        // Criar registros manualmente de exemplo:
        /*
        Tag::create([
            'nome' => 'Exemplo de Tag',
            // Adicione mais campos conforme necessário
        ]);
        */

        // Exemplo com relacionamentos:
        /*
        $relatedModel = RelatedModel::first();
        if ($relatedModel) {
            Tag::create([
                'nome' => 'Exemplo com relação',
                'related_model_id' => $relatedModel->id,
                // Outros campos...
            ]);
        }
        */
    }
}
