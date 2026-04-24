<?php

namespace App\Domains\Catalog\Seeders;

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use Illuminate\Database\Seeder;

class AttributeSeeder extends Seeder
{
    public function run(): void
    {
        $defs = [
            ['code' => 'product_type', 'label' => 'Tipo de Produto', 'type' => AttributeType::Facet, 'input' => AttributeInputType::Select, 'order' => 1],
            ['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input' => AttributeInputType::Select, 'order' => 2],
            ['code' => 'team', 'label' => 'Time', 'type' => AttributeType::Facet, 'input' => AttributeInputType::Multiselect, 'order' => 3],
            ['code' => 'sport', 'label' => 'Esporte', 'type' => AttributeType::Facet, 'input' => AttributeInputType::Multiselect, 'order' => 4],
            ['code' => 'age_group', 'label' => 'Idade', 'type' => AttributeType::Facet, 'input' => AttributeInputType::Select, 'order' => 5],
            ['code' => 'material', 'label' => 'Material', 'type' => AttributeType::Facet, 'input' => AttributeInputType::Multiselect, 'order' => 6],
            ['code' => 'collar', 'label' => 'Gola', 'type' => AttributeType::Facet, 'input' => AttributeInputType::Select, 'order' => 7],
            ['code' => 'pocket', 'label' => 'Bolso', 'type' => AttributeType::Facet, 'input' => AttributeInputType::Select, 'order' => 8],
            ['code' => 'sleeve', 'label' => 'Manga', 'type' => AttributeType::Facet, 'input' => AttributeInputType::Select, 'order' => 9],
            ['code' => 'cap_brim', 'label' => 'Aba', 'type' => AttributeType::Facet, 'input' => AttributeInputType::Select, 'order' => 10],
            ['code' => 'color', 'label' => 'Cor', 'type' => AttributeType::Variant, 'input' => AttributeInputType::Swatch, 'order' => 11],
            ['code' => 'size', 'label' => 'Tamanho', 'type' => AttributeType::Variant, 'input' => AttributeInputType::Select, 'order' => 12],
        ];

        foreach ($defs as $def) {
            Attribute::updateOrCreate(
                ['code' => $def['code']],
                [
                    'label' => $def['label'],
                    'type' => $def['type'],
                    'input_type' => $def['input'],
                    'is_filterable' => true,
                    'display_order' => $def['order'],
                ]
            );
        }
    }
}
