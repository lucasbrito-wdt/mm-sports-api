<?php

namespace App\Domains\Catalog\Seeders;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class AttributeValueSeeder extends Seeder
{
    public function run(): void
    {
        $data = [
            'product_type' => ['Camisa', 'Camiseta', 'Short', 'Bermuda', 'Meia', 'Boné', 'Jaqueta', 'Agasalho', 'Tênis'],
            'brand' => ['Nike', 'Adidas', 'Puma', 'Topper', 'Umbro', 'Mizuno', 'Penalty'],
            'team' => ['Corinthians', 'Palmeiras', 'São Paulo', 'Santos', 'Flamengo', 'Vasco', 'Fluminense', 'Botafogo'],
            'sport' => ['Futebol', 'Basquete', 'Corrida', 'Vôlei', 'Tênis', 'Natação'],
            'age_group' => ['Infantil', 'Juvenil', 'Adulto'],
            'material' => ['Poliéster', 'Algodão', 'Dry-fit', 'Couro sintético', 'Nylon'],
            'collar' => ['Redonda', 'V', 'Polo', 'Gola alta'],
            'pocket' => ['Com bolso', 'Sem bolso'],
            'sleeve' => ['Curta', 'Longa', 'Sem manga', '3/4'],
            'cap_brim' => ['Reta', 'Curva', 'Sem aba'],
            'size' => ['PP', 'P', 'M', 'G', 'GG', 'XG'],
            'color' => [
                ['value' => 'Azul', 'hex' => '#1E3A8A'],
                ['value' => 'Vermelho', 'hex' => '#B91C1C'],
                ['value' => 'Preto', 'hex' => '#111111'],
                ['value' => 'Branco', 'hex' => '#FFFFFF'],
                ['value' => 'Verde', 'hex' => '#15803D'],
                ['value' => 'Amarelo', 'hex' => '#FACC15'],
            ],
        ];

        foreach ($data as $code => $values) {
            $attribute = Attribute::where('code', $code)->firstOrFail();

            foreach ($values as $order => $entry) {
                $isColor = is_array($entry);
                $value = $isColor ? $entry['value'] : $entry;
                $metadata = $isColor ? ['hex' => $entry['hex']] : null;

                AttributeValue::updateOrCreate(
                    ['attribute_id' => $attribute->id, 'slug' => Str::slug($value)],
                    [
                        'value' => $value,
                        'metadata' => $metadata,
                        'display_order' => $order,
                    ]
                );
            }
        }
    }
}
