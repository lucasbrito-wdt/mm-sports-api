<?php

namespace App\Domains\BlogComplete\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Domains\BlogComplete\Models\Post;


class PostSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds for Post.
     *
     * @return void
     */
    public function run(): void
    {
        // Para usar factories, crie o arquivo de factory correspondente:
        // Post::factory(10)->create();

        // Criar registros manualmente de exemplo:
        /*
        Post::create([
            'nome' => 'Exemplo de Post',
            // Adicione mais campos conforme necessário
        ]);
        */

        // Exemplo com relacionamentos:
        /*
        $relatedModel = RelatedModel::first();
        if ($relatedModel) {
            Post::create([
                'nome' => 'Exemplo com relação',
                'related_model_id' => $relatedModel->id,
                // Outros campos...
            ]);
        }
        */
    }
}
