<?php

namespace App\Domains\BlogComplete\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Domains\BlogComplete\Models\Comment;
use App\Domains\BlogComplete\Models\Post;


class CommentSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds for Comment.
     *
     * @return void
     */
    public function run(): void
    {
        // Para usar factories, crie o arquivo de factory correspondente:
        // Comment::factory(10)->create();

        // Criar registros manualmente de exemplo:
        /*
        Comment::create([
            'nome' => 'Exemplo de Comment',
            // Adicione mais campos conforme necessário
        ]);
        */

        // Exemplo com relacionamentos:
        /*
        $relatedModel = RelatedModel::first();
        if ($relatedModel) {
            Comment::create([
                'nome' => 'Exemplo com relação',
                'related_model_id' => $relatedModel->id,
                // Outros campos...
            ]);
        }
        */
    }
}
