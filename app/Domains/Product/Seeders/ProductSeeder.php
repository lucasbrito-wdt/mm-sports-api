<?php

namespace App\Domains\Product\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use App\Domains\Product\Models\Product;
use App\Domains\Category\Models\Category;


class ProductSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Run the database seeds for Product.
     *
     * @return void
     */
    public function run(): void
    {
        // Para usar factories, crie o arquivo de factory correspondente:
        // Product::factory(10)->create();

        // Criar registros manualmente de exemplo:
        /*
        Product::create([
            'nome' => 'Exemplo de Product',
            // Adicione mais campos conforme necessário
        ]);
        */

        // Exemplo com relacionamentos:
        /*
        $relatedModel = RelatedModel::first();
        if ($relatedModel) {
            Product::create([
                'nome' => 'Exemplo com relação',
                'related_model_id' => $relatedModel->id,
                // Outros campos...
            ]);
        }
        */
    }
}
