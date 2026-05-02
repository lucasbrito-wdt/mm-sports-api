<?php

namespace App\Domains\Catalog\Seeders;

use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class ProductDemoSeeder extends Seeder
{
    public function run(): void
    {
        $this->seedExtraAttributeValues();

        $categoryIds = Category::pluck('id', 'slug');
        $brandValueIds = AttributeValue::whereHas(
            'attribute', fn ($q) => $q->where('code', 'brand')
        )->pluck('id', 'value');

        foreach ($this->products() as $data) {
            $slug = Str::slug($data['nome']);
            $origin = $this->guessOrigin($data['categoria']);

            $product = Product::updateOrCreate(
                ['slug' => $slug],
                [
                    'title'                  => $data['nome'],
                    'origin'                 => $origin,
                    'allows_personalization' => false,
                    'status'                 => ProductStatus::Published,
                    'category_id'            => $categoryIds[$data['categoria']] ?? null,
                ]
            );

            ProductImage::updateOrCreate(
                ['product_id' => $product->id, 'display_order' => 0],
                ['url' => $data['imagem'], 'alt' => $data['nome']]
            );

            foreach ($this->sizesFor($data['categoria']) as $size) {
                $sku = 'MM-' . str_pad((string) $data['id'], 4, '0', STR_PAD_LEFT) . '-' . strtoupper($size);
                ProductVariant::updateOrCreate(
                    ['sku' => $sku],
                    [
                        'product_id'       => $product->id,
                        'price'            => $data['preco'],
                        'compare_at_price' => $data['precoAntes'] ?? null,
                        'stock_quantity'   => rand(5, 50),
                        'is_active'        => true,
                        'attribute_payload' => json_encode(['size' => $size]),
                    ]
                );
            }

            $brandName = $this->guessBrand($data['nome']);
            if ($brandName && isset($brandValueIds[$brandName])) {
                $product->attributeValues()->syncWithoutDetaching([$brandValueIds[$brandName]]);
                $product->refreshAttributeValueIdsCache();
            }
        }
    }

    private function seedExtraAttributeValues(): void
    {
        $brandAttr = Attribute::where('code', 'brand')->first();
        if ($brandAttr) {
            $extraBrands = ['Reebok', 'Volt', 'Joma', 'New Era', 'Diadora', 'Skechers', 'Umbro', 'Under Armour'];
            foreach ($extraBrands as $i => $brand) {
                AttributeValue::updateOrCreate(
                    ['attribute_id' => $brandAttr->id, 'slug' => Str::slug($brand)],
                    ['value' => $brand, 'display_order' => 20 + $i]
                );
            }
        }

        $teamAttr = Attribute::where('code', 'team')->first();
        if ($teamAttr) {
            $extraTeams = [
                'Brasil', 'Argentina', 'Botafogo', 'Corinthians', 'Flamengo',
                'Ponte Preta', 'Náutico', 'Criciúma', 'PSG', 'Atlético Madrid',
                'Chelsea', 'Manchester City', 'Barcelona',
            ];
            foreach ($extraTeams as $i => $team) {
                AttributeValue::updateOrCreate(
                    ['attribute_id' => $teamAttr->id, 'slug' => Str::slug($team)],
                    ['value' => $team, 'display_order' => 20 + $i]
                );
            }
        }
    }

    private function guessOrigin(string $categoria): ProductOrigin
    {
        return match ($categoria) {
            'times-europeus', 'chuteiras', 'bones-e-gorros' => ProductOrigin::Imported,
            default => ProductOrigin::National,
        };
    }

    private function guessBrand(string $nome): ?string
    {
        foreach (['New Era', 'Adidas', 'Nike', 'Reebok', 'Puma', 'Penalty', 'Joma', 'Volt', 'Diadora', 'Skechers', 'Umbro', 'Mizuno'] as $brand) {
            if (mb_stripos($nome, $brand) !== false) {
                return $brand;
            }
        }

        return null;
    }

    private function sizesFor(string $categoria): array
    {
        return match ($categoria) {
            'chuteiras'     => ['37', '38', '39', '40', '41', '42'],
            'bones-e-gorros' => ['Único'],
            default         => ['P', 'M', 'G', 'GG'],
        };
    }

    private function products(): array
    {
        return [
            // ── SELEÇÕES ────────────────────────────────────────────────────────────
            // Brasil
            ['id' => 1,   'nome' => 'Camisa Brasil Retrô 1970 Amarela e Verde',                              'categoria' => 'selecoes',        'preco' => 229.9, 'precoAntes' => 189.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_retro_1970_amarela_e_verde_158174_1_73a8205e46131d3c48ecbca4dce5f281.jpg'],
            ['id' => 2,   'nome' => 'Camisa Volt Brasil Vôlei CBV 2025 Feminina Amarela',                    'categoria' => 'selecoes',        'preco' => 229.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_volt_brasil_volei_cbv_2025_feminina_amarela_159733_1_e54d2cbd22885c1591a1f2d17eb96731.jpg'],
            ['id' => 3,   'nome' => 'Camisa Volt Brasil Vôlei CBV 2025 Feminina Marinho',                    'categoria' => 'selecoes',        'preco' => 229.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_volt_brasil_volei_cbv_2025_feminina_marinho_159741_1_8b42d2e2c69b6d5c597e8c0537577f78.jpg'],
            ['id' => 4,   'nome' => 'Camisa Volt Brasil Vôlei CBV 2025 Feminina Branca',                     'categoria' => 'selecoes',        'preco' => 229.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_volt_brasil_volei_cbv_2025_feminina_branca_159743_1_4db5679372e89056dede0dc01388995c.jpg'],
            ['id' => 5,   'nome' => 'Camisa Volt Brasil Vôlei CBV 2025 Infantil Amarela',                    'categoria' => 'selecoes',        'preco' => 179.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_volt_brasil_volei_cbv_2025_infantil_amarela_159734_1_e9765f2aae62c23c907345c256c77bce.jpg'],
            ['id' => 6,   'nome' => 'Regata Volt Brasil Vôlei CBV 2025 Feminina Azul',                       'categoria' => 'selecoes',        'preco' => 189.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_regata_volt_brasil_volei_cbv_2025_feminina_azul_159889_1_9028fd94951fb1cc3d40bcdc28702cb4.jpg'],
            ['id' => 7,   'nome' => 'Camisa Rei Pelé 10 Amarela',                                            'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_rei_pele_10_amarela_117475_1_b0b3083ae6da017f3774958ec93d6501.jpg'],
            ['id' => 8,   'nome' => 'Camisa Brasil 20 Vini Jr Amarela',                                      'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_20_vini_jr_amarela_116140_1_45b2e3bf41ff5ccaf54bc371c6c33f4b.jpg'],
            ['id' => 9,   'nome' => 'Camisa Brasil Marta 10 Amarela',                                        'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_bandeira_marta_10_amarela_121512_1_494a19f29b6fa84b6e067bf608e30960.jpg'],
            ['id' => 10,  'nome' => 'Camisa Brasil Debinha 9 Amarela',                                       'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_debinha_9_amarela_125773_1_dc8a18dbb294f3aaaacc832cd8e340bc.jpg'],
            ['id' => 11,  'nome' => 'Camisa Brasil Geyse 18 Amarela',                                        'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_geyse_18_amarela_125774_1_5851e2fcede70148a670cac20e890d31.jpg'],
            ['id' => 12,  'nome' => 'Camisa Brasil Zaneratto 16 Amarela',                                    'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_zaneratto_16_amarela_125772_1_8b01fb5365823c78224448e5204c7078.jpg'],
            ['id' => 13,  'nome' => 'Camisa Brasil Ary 17 Amarela',                                          'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_ary_17_amarela_125771_1_d1270f895e3ab88cc421bf2bb4196989.jpg'],
            ['id' => 14,  'nome' => 'Camisa Brasil Tamires 6 Amarela',                                       'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_tamires_6_amarela_125770_1_9bbab90513f187f90e9bde0708acc32b.jpg'],
            ['id' => 15,  'nome' => 'Camisa Brasil Clássica Amarela Feminina',                               'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_classica_amarela_feminina_115627_1_79284e97c746e4e4d02848155da6335f.jpg'],
            ['id' => 16,  'nome' => 'Camisa Brasil Estrelas Amarela Feminina',                               'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_estrelas_amarela_feminina_115624_1_2f4d317b29f35031361c002d3c3a2f2f.jpg'],
            ['id' => 17,  'nome' => 'Camisa Brasil Bandeira Amarela Feminina',                               'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_bandeira_amarela_feminina_115623_1_fd392ee5549fc61db5034fdca0698929.jpg'],
            ['id' => 18,  'nome' => 'Camisa Brasil Trofeuzinho Amarela',                                     'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_trofeuzinho_amarela_113646_1_8918c24e4427e6c63368dbace6162f4f.jpg'],
            ['id' => 19,  'nome' => 'Camisa Brasil Defante Amarela',                                         'categoria' => 'selecoes',        'preco' => 69.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_defante_amarela_116141_1_f2d609669ce3288e614835e0b0e1d2a3.jpg'],
            ['id' => 20,  'nome' => 'Camisa Brasil 10 Neymar Azul Infantil',                                 'categoria' => 'selecoes',        'preco' => 59.9,  'precoAntes' => 39.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_brasil_10_neymar_azul_infantil_115111_1_9a7747630b9b84acddf22979f771203b.jpg'],
            ['id' => 21,  'nome' => 'Kit de 2 Camisas Placar Brasil Azul e Amarela Feminina',                'categoria' => 'selecoes',        'preco' => 139.8, 'precoAntes' => 62.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_de_2_camisas_placar_brasil_azul_e_amarela_feminina_149338_1_87406e41a37ff3ac95ae08a5f02b2c51.jpg'],
            ['id' => 22,  'nome' => 'Conjunto Brasil Baby N10 Flag',                                         'categoria' => 'selecoes',        'preco' => 169.9, 'precoAntes' => 149.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_conjunto_brasil_baby_n10_flag_113136_1_3a3bba5a26593ad09635a81b01ce54bc.jpg'],
            // Argentina
            ['id' => 23,  'nome' => 'Camisa Adidas Argentina Home 2026',                                     'categoria' => 'selecoes',        'preco' => 399.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_adidas_argentina_home_2026_2_20251106082052_238d8940aca0.jpg'],
            ['id' => 24,  'nome' => 'Camisa Adidas Argentina Pré-Jogo II 2026 Azul',                         'categoria' => 'selecoes',        'preco' => 299.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_adidas_argentina_pr_jogo_ii_2026_azul_1_20260331113343_f59649e10fa3.jpg'],
            ['id' => 25,  'nome' => 'Camisa Adidas Argentina Edição Comemorativa 2025 Feminina',              'categoria' => 'selecoes',        'preco' => 399.9, 'precoAntes' => 599.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_adidas_argentina_edio_comemorativa_2025_fem_1_20250825132858_72615fa28adb.jpg'],
            ['id' => 26,  'nome' => 'Camisa Argentina Retrô Copa 1986 Azul',                                 'categoria' => 'selecoes',        'preco' => 129.9, 'precoAntes' => 169.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_argentina_retro_copa_1986_azul_147046_1_df19371a378e47d9cdd8cfc54473be43.jpg'],
            ['id' => 27,  'nome' => 'Camiseta Adidas Argentina DNA Juvenil',                                 'categoria' => 'selecoes',        'preco' => 179.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camiseta_adidas_argentina_dna_juvenil_4_20251107110447_db15f95b2646.jpg'],
            ['id' => 28,  'nome' => 'Camiseta Adidas Argentina DNA Branca',                                  'categoria' => 'selecoes',        'preco' => 159.9, 'precoAntes' => 199.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camiseta_adidas_argentina_dna_branca_1_20260113155327_89d6d509d34e.jpg'],
            ['id' => 29,  'nome' => 'Camiseta Adidas Argentina GR DNA Branca',                               'categoria' => 'selecoes',        'preco' => 149.9, 'precoAntes' => 199.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camiseta_adidas_argentina_gr_dna_branca_1_20251107151340_653d852a6188.jpg'],
            ['id' => 30,  'nome' => 'Jaqueta Adidas Argentina Apresentação Juvenil Marinho',                 'categoria' => 'selecoes',        'preco' => 339.9, 'precoAntes' => 399.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_jaqueta_adidas_argentina_apresentao_juvenil_marinh_1_20251117093429_028dbf1e5492.jpg'],
            ['id' => 31,  'nome' => 'Jaqueta Adidas Argentina Apresentação Tiro 26 Marinho',                 'categoria' => 'selecoes',        'preco' => 399.9, 'precoAntes' => 499.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_jaqueta_adidas_argentina_apresentao_tiro_26_marinh_1_20251121093410_042ea7525e84.jpg'],
            ['id' => 32,  'nome' => 'Moletom Adidas Argentina Juvenil Azul',                                 'categoria' => 'selecoes',        'preco' => 239.9, 'precoAntes' => 299.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_moletom_adidas_argentina_juvenil_azul_1_20251124163337_a60817cb030f.jpg'],
            ['id' => 33,  'nome' => 'Moletom Adidas Argentina DNA Azul',                                     'categoria' => 'selecoes',        'preco' => 369.9, 'precoAntes' => 399.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_moletom_adidas_argentina_dna_azul_1_20251110162243_6a8aaa72ce3a.jpg'],
            ['id' => 34,  'nome' => 'Calção Adidas Argentina Treino 2026 Juvenil Azul Marinho',              'categoria' => 'selecoes',        'preco' => 159.9, 'precoAntes' => 179.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_calo_adidas_argentina_treino_2026_juvenil_azul_mar_1_20251111154921_67ddeb57e7a3.jpg'],
            ['id' => 35,  'nome' => 'Calção Adidas Argentina Home 2026 Branco',                              'categoria' => 'selecoes',        'preco' => 249.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_calo_adidas_argentina_home_2026_branco_1_20251120120042_7109fd21b999.jpg'],
            ['id' => 36,  'nome' => 'Calção Adidas Argentina Home 2026',                                     'categoria' => 'selecoes',        'preco' => 249.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_calo_adidas_argentina_home_2026_1_20260113160042_5beb938b32ab.jpg'],

            // ── TIMES NACIONAIS ─────────────────────────────────────────────────────
            // Botafogo
            ['id' => 37,  'nome' => 'Camisa Reebok Botafogo II 2025 Campeão Brasileiro 2024',                'categoria' => 'times-nacionais', 'preco' => 329.9, 'precoAntes' => 479.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_ii_2025_campeo_brasileiro_2_1_20251017161854_3f6cd9afef35.jpg'],
            ['id' => 38,  'nome' => 'Camisa Reebok Botafogo II 2025 Feminina Campeão Libertadores 2024',     'categoria' => 'times-nacionais', 'preco' => 479.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_ii_2025_feminina_campeo_lib_1_20251024083909_4abd49a37813.jpg'],
            ['id' => 39,  'nome' => 'Camisa Reebok Botafogo I 2025 Juvenil Campeão Libertadores 2024',       'categoria' => 'times-nacionais', 'preco' => 409.9, 'precoAntes' => 479.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_i_2025_juvenil_campeo_liber_1_20251023112406_a3ef1bf6d3cb.jpg'],
            ['id' => 40,  'nome' => 'Camisa Reebok Botafogo I 2025 Juvenil',                                 'categoria' => 'times-nacionais', 'preco' => 299.9, 'precoAntes' => 399.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_i_2025_juvenil_1_20250812164505_fbbf2fc9c204.jpg'],
            ['id' => 41,  'nome' => 'Camisa Reebok Botafogo I 2025 Patch Campeão Brasileiro',                'categoria' => 'times-nacionais', 'preco' => 329.9, 'precoAntes' => 479.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebook_botafogo_i_2025_patch_campeao_brasileiro_160617_1_1b99171449b19e0e4a8ba9bd1f93310c.jpg'],
            ['id' => 42,  'nome' => 'Camisa Reebok Botafogo I 2025 98 A. Cabral',                            'categoria' => 'times-nacionais', 'preco' => 289.9, 'precoAntes' => 439.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_i_2025_98_a_cabral_160643_1_3b6ea52bf7ca5818956f26ca16162a95.jpg'],
            ['id' => 43,  'nome' => 'Camisa Reebok Botafogo I 2025 30 J. Correa',                            'categoria' => 'times-nacionais', 'preco' => 289.9, 'precoAntes' => 439.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_i_2025_30_j_correa_160642_1_5396b628b574bf310d8b63fe246ddfe4.jpg'],
            ['id' => 44,  'nome' => 'Camisa Reebok Botafogo I 2025 10 Savarino',                             'categoria' => 'times-nacionais', 'preco' => 289.9, 'precoAntes' => 439.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_i_2025_10_savarino_160641_1_336635e0e4ee235afbc1c852091ad40e.jpg'],
            ['id' => 45,  'nome' => 'Camisa Reebok Botafogo FR I 2025 7 Artur',                              'categoria' => 'times-nacionais', 'preco' => 289.9, 'precoAntes' => 439.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_fr_i_2025_7_artur_160640_1_ff8142228aa17321651cfe8d10fe385d.jpg'],
            ['id' => 46,  'nome' => 'Camisa Reebok Botafogo I 2025 25 Allan',                                'categoria' => 'times-nacionais', 'preco' => 289.9, 'precoAntes' => 439.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_i_2025_25_allan_160639_1_f32b2136201a72fb05f9a262afd15c34.jpg'],
            ['id' => 47,  'nome' => 'Camisa Reebok Botafogo I 2025 17 Marlon Freitas',                       'categoria' => 'times-nacionais', 'preco' => 289.9, 'precoAntes' => 439.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_i_2025_17_marlon_freitas_160638_1_b149728190531aef471acbb4ff113c6c.jpg'],
            ['id' => 48,  'nome' => 'Camisa Reebok Botafogo I 2025 13 Alex Telles',                          'categoria' => 'times-nacionais', 'preco' => 289.9, 'precoAntes' => 439.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_i_2025_13_alex_telles_160637_1_aff37e80ca33999676a55795bc515597.jpg'],
            ['id' => 49,  'nome' => 'Camisa Reebok Botafogo I 2025 47 Jeffinho',                             'categoria' => 'times-nacionais', 'preco' => 289.9, 'precoAntes' => 439.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_i_2025_47_jeffinho_160636_1_dc261f6571540b017427c60d05662220.jpg'],
            ['id' => 50,  'nome' => 'Camisa Reebok Botafogo I 2025 5 Danilo',                                'categoria' => 'times-nacionais', 'preco' => 289.9, 'precoAntes' => 439.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_reebok_botafogo_i_2025_5_danilo_160635_1_3d1ab3baea77d1b092ae760cb4ae29a2.jpg'],
            ['id' => 51,  'nome' => 'Bucket New Era Botafogo Preto',                                         'categoria' => 'times-nacionais', 'preco' => 139.9, 'precoAntes' => 299.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bucket_new_era_botafogo_preto_161679_1_8f45504018872cb7c393746480308538.jpg'],
            ['id' => 52,  'nome' => 'Boneco Mascote Botafogo',                                               'categoria' => 'times-nacionais', 'preco' => 54.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_boneco_mascote_botafogo_1_20251103103756_76e2e92040b1.jpg'],
            ['id' => 53,  'nome' => 'Kit com 2 Toalhas de Banho Bouton Botafogo',                            'categoria' => 'times-nacionais', 'preco' => 189.8, 'precoAntes' => 259.8,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_com_2_toalhas_de_banho_bouton_botafogo_1_20251021074648_f5344ef5b52e.jpg'],
            ['id' => 54,  'nome' => 'Kit com 2 Bonés Botafogo',                                              'categoria' => 'times-nacionais', 'preco' => 169.8, 'precoAntes' => 219.8,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_com_2_bons_botafogo_1_20251020165212_c3366f418da1.jpg'],
            ['id' => 55,  'nome' => 'Kit 2 Babadores Botafogo',                                              'categoria' => 'times-nacionais', 'preco' => 44.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_2_babadores_botafogo_1_20251020112433_114eca2bf9d3.jpg'],
            ['id' => 56,  'nome' => 'Macacão Regata Infantil Botafogo Branco',                               'categoria' => 'times-nacionais', 'preco' => 78.9,  'precoAntes' => 89.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_macaco_regata_infantil_botafogo_branco_1_20251022111230_8403d6a34135.jpg'],
            ['id' => 57,  'nome' => 'Kit Body Botafogo 3 Peças Infantil',                                    'categoria' => 'times-nacionais', 'preco' => 109.9, 'precoAntes' => 149.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_body_botafogo_3_peas_infantil_1_20251020083758_e8c2ac5121f6.jpg'],
            ['id' => 58,  'nome' => 'Kit Body Longo Calça e Pantufa Infantil Botafogo',                      'categoria' => 'times-nacionais', 'preco' => 109.9, 'precoAntes' => 149.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_body_longo_cala_e_pantufa_infantil_botafogo_1_20251020142258_e5995ad01059.jpg'],
            ['id' => 59,  'nome' => 'Kit Botafogo Necessaire Nylon e Chaveiro Luva',                         'categoria' => 'times-nacionais', 'preco' => 89.9,  'precoAntes' => 108.8,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_botafogo_necessaire_nylon_e_chaveiro_luva_161825_1_8a79c5ae78bd4c38ed9cfedbf35bc5be.jpg'],
            ['id' => 60,  'nome' => 'Kit Botafogo Porta Chuteira e Squeeze Alumínio',                        'categoria' => 'times-nacionais', 'preco' => 119.9, 'precoAntes' => 179.8,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_botafogo_porta_chuteira_e_squeeze_aluminio_161824_1_c549e477d568006001c64084598e938e.jpg'],
            // Corinthians
            ['id' => 61,  'nome' => 'Camisa Corinthians Retrô 1995 Listrada',                                'categoria' => 'times-nacionais', 'preco' => 199.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_retr_1995_listrada_1_20260108120205_aedd6f72af6f.jpg'],
            ['id' => 62,  'nome' => 'Camisa Corinthians Retrô 1998 DDD',                                     'categoria' => 'times-nacionais', 'preco' => 219.9, 'precoAntes' => 199.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_retr_1998_ddd_1_20260108102518_dedb801e4fbd.jpg'],
            ['id' => 63,  'nome' => 'Camisa Corinthians Retrô 1914 Cordinha',                                'categoria' => 'times-nacionais', 'preco' => 219.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_retr_1914_cordinha_1_20260108102629_ed572904247e.jpg'],
            ['id' => 64,  'nome' => 'Camisa Corinthians Versão Neon Preta e Laranja',                        'categoria' => 'times-nacionais', 'preco' => 149.9, 'precoAntes' => 139.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_verso_neon_preta_e_laranja_1_20260108102438_7faf22b2b6bb.jpg'],
            ['id' => 65,  'nome' => 'Camisa Corinthians Logo Dry Preta',                                     'categoria' => 'times-nacionais', 'preco' => 159.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_logo_dry_preta_1_20260122160102_941f15d8d6cb.jpg'],
            ['id' => 66,  'nome' => 'Camiseta Corinthians São Jorge Oversized Branca',                       'categoria' => 'times-nacionais', 'preco' => 139.9, 'precoAntes' => 119.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camiseta_corinthians_so_jorge_oversized_branca_1_20251210173838_41b3033c1a4f.jpg'],
            ['id' => 67,  'nome' => 'Camisa Corinthians Classic 10 Memphis Preta e Dourada',                 'categoria' => 'times-nacionais', 'preco' => 189.9, 'precoAntes' => 169.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_classic_10_memphis_preta_e_dour_1_20250923080040_bdb007d2d4fd.jpg'],
            ['id' => 68,  'nome' => 'Camisa Corinthians Classic 9 Yuri Alberto Preta e Dourada',             'categoria' => 'times-nacionais', 'preco' => 189.9, 'precoAntes' => 169.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_classic_9_yuri_alberto_preta_e_1_20250923080058_21b5df29ad5c.jpg'],
            ['id' => 69,  'nome' => 'Camisa Corinthians Classic 8 Garro Preta e Dourada',                    'categoria' => 'times-nacionais', 'preco' => 189.9, 'precoAntes' => 169.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_classic_8_garro_preta_e_dourada_1_20250923080019_be2f570a4072.jpg'],
            ['id' => 70,  'nome' => 'Camisa Corinthians Lines 10 Memphis Preta',                             'categoria' => 'times-nacionais', 'preco' => 169.9, 'precoAntes' => 129.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_lines_10_memphis_preta_1_20250923080300_5793f337a241.jpg'],
            ['id' => 71,  'nome' => 'Camisa Corinthians Lines 9 Yuri Alberto Preta',                         'categoria' => 'times-nacionais', 'preco' => 169.9, 'precoAntes' => 129.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_lines_9_yuri_alberto_preta_1_20250923080315_b160f48441d9.jpg'],
            ['id' => 72,  'nome' => 'Camisa Corinthians Lines 8 Garro Preta',                                'categoria' => 'times-nacionais', 'preco' => 169.9, 'precoAntes' => 129.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_lines_8_garro_preta_1_20250923080242_ee7d41f1df01.jpg'],
            ['id' => 73,  'nome' => 'Camisa Corinthians CP 10 Memphis Infantil Preta',                       'categoria' => 'times-nacionais', 'preco' => 169.9, 'precoAntes' => 159.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_cp_10_memphis_infantil_preta_1_20250923080509_5dd5833d2af5.jpg'],
            ['id' => 74,  'nome' => 'Camisa Corinthians CP 9 Yuri Alberto Infantil Preta',                   'categoria' => 'times-nacionais', 'preco' => 169.9, 'precoAntes' => 159.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_cp_9_yuri_alberto_infantil_pret_1_20250923080525_8eb6f281ec5a.jpg'],
            ['id' => 75,  'nome' => 'Camisa Corinthians CP 8 Garro Infantil Preta',                          'categoria' => 'times-nacionais', 'preco' => 169.9, 'precoAntes' => 159.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_corinthians_cp_8_garro_infantil_preta_1_20250923080455_950d1bc568b8.jpg'],
            ['id' => 76,  'nome' => 'Body Corinthians Proteção UV Manga Longa Infantil Preto',               'categoria' => 'times-nacionais', 'preco' => 119.9, 'precoAntes' => 109.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_body_corinthians_proteo_uv_manga_longa_infantil_pr_1_20251022143522_e468e7c288bf.jpg'],
            ['id' => 77,  'nome' => 'Macacão Meia Malha Curto Corinthians',                                  'categoria' => 'times-nacionais', 'preco' => 119.9, 'precoAntes' => 78.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_macaco_meia_malha_curto_corinthians_1_20251022113321_7ec99d306097.jpg'],
            ['id' => 78,  'nome' => 'Kit com 2 Bonés Corinthians',                                           'categoria' => 'times-nacionais', 'preco' => 199.8, 'precoAntes' => 159.8,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_com_2_bons_corinthians_1_20251009154627_0ea4115ce69f.jpg'],
            ['id' => 79,  'nome' => 'Kit com 2 Bonés Escudo Corinthians',                                    'categoria' => 'times-nacionais', 'preco' => 199.8, 'precoAntes' => 159.8,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_com_2_bons_escudo_corinthians_1_20251009152647_5f2bcc57521b.jpg'],
            ['id' => 80,  'nome' => 'Bola Corinthians Mundial 2.0 Campo Preta e Branca',                     'categoria' => 'times-nacionais', 'preco' => 109.9, 'precoAntes' => 89.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bola_corinthians_mundial_20_campo_preta_e_branca_1_20250919100712_8992d8646fdf.jpg'],
            ['id' => 81,  'nome' => 'Meia Esporte Corinthians Escudo Branca',                                'categoria' => 'times-nacionais', 'preco' => 25.9,  'precoAntes' => 19.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_meia_esporte_corinthians_escudo_branca_1_20250926125812_63629fd8d218.jpg'],
            ['id' => 82,  'nome' => 'Meia Corinthians Esportiva Escudo Preta',                               'categoria' => 'times-nacionais', 'preco' => 39.9,  'precoAntes' => 32.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_meia_corinthians_esportiva_escudo_preta_1_20251015081219_f6eeb3e002b6.jpg'],
            ['id' => 83,  'nome' => 'Meia Corinthians Cano Alto Preta e Amarelo',                            'categoria' => 'times-nacionais', 'preco' => 49.9,  'precoAntes' => 29.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_meia_corinthians_cano_alto_preta_e_amarelo_1_20251027095526_dd92fa0833bc.jpg'],
            ['id' => 84,  'nome' => 'Meia Corinthians Casual Juvenil Branca',                                'categoria' => 'times-nacionais', 'preco' => 29.9,  'precoAntes' => 19.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_meia_corinthians_casual_juvenil_branca_1_20251118134620_1eda06f5b6b3.jpg'],
            // Outros nacionais
            ['id' => 85,  'nome' => 'Polo Flamengo Júnior 92 Infantil Branca',                               'categoria' => 'times-nacionais', 'preco' => 169.9, 'precoAntes' => 189.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_polo_flamengo_jnior_92_infantil_branca_1_20260323134126_919cae5d46de.jpg'],
            ['id' => 86,  'nome' => 'Camisa Diadora Ponte Preta Aquecimento Atleta 2025',                    'categoria' => 'times-nacionais', 'preco' => 159.9, 'precoAntes' => 249.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_diadora_ponte_preta_aquecimento_atleta_2025_1_20260410103340_c0aa5500e304.jpg'],
            ['id' => 87,  'nome' => 'Camisa Diadora Ponte Preta Treino Linha 2025',                          'categoria' => 'times-nacionais', 'preco' => 179.9, 'precoAntes' => 249.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_diadora_ponte_preta_treino_linha_2025_1_20260410074723_1b2be282d7e8.jpg'],
            ['id' => 88,  'nome' => 'Camisa Diadora Náutico Capibaribe I 2025 Feminina',                     'categoria' => 'times-nacionais', 'preco' => 149.9, 'precoAntes' => 249.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_diadorai_nutico_2025_feminina_1_20260402080817_91c330573947.jpg'],
            ['id' => 89,  'nome' => 'Camisa Volt Criciúma Attack 2025',                                      'categoria' => 'times-nacionais', 'preco' => 149.9, 'precoAntes' => 159.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_volt_cricima_attack_2025_1_20260416143752_3a5f72d3ec4a.jpg'],

            // ── TIMES EUROPEUS ──────────────────────────────────────────────────────
            // PSG
            ['id' => 90,  'nome' => 'Camisa PSG Digital Infantil Azul Marinho',                              'categoria' => 'times-europeus',  'preco' => 44.9,  'precoAntes' => 89.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_psg_digital_infantil_azul_marinho_1_20260113154002_42db0160986d.jpg'],
            ['id' => 91,  'nome' => 'Camisa PSG Digital Infantil Branca',                                    'categoria' => 'times-europeus',  'preco' => 49.9,  'precoAntes' => 119.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_psg_digital_infantil_branca_1_20260113083421_427ba0cd1ec0.jpg'],
            ['id' => 92,  'nome' => 'Camisa PSG Altcoin Infantil Vermelha',                                  'categoria' => 'times-europeus',  'preco' => 36.9,  'precoAntes' => 69.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_psg_altcoin_infantil_vermelha_1_20260113091519_685e26c92dcc.jpg'],
            ['id' => 93,  'nome' => 'Camisa PSG Dryfit Marinho',                                             'categoria' => 'times-europeus',  'preco' => 99.9,  'precoAntes' => 119.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_psg_dryfit_marinho_157243_1_e48f678b8772fde05bd747bb07a59456.jpg'],
            ['id' => 94,  'nome' => 'Camisa PSG Dryfit Juvenil Marinho',                                     'categoria' => 'times-europeus',  'preco' => 89.9,  'precoAntes' => 109.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_psg_dryfit_juvenil_marinho_1_20250812075941_7af2c09cca94.jpg'],
            ['id' => 95,  'nome' => 'Camisa PSG Polygon Infantil Preta',                                     'categoria' => 'times-europeus',  'preco' => 99.9,  'precoAntes' => 129.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_psg_polygon_infantil_preta_121810_1_3ee59b2391a2c9b6d02b248b168c4a43.jpg'],
            ['id' => 96,  'nome' => 'Camisa PSG Draft Infantil Preta e Branca',                              'categoria' => 'times-europeus',  'preco' => 39.9,  'precoAntes' => 89.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_psg_draft_infantil_preta_e_branca_121812_1_cc99bf42dfc0327a079b5c0640fca6af.jpg'],
            ['id' => 97,  'nome' => 'Camisa PSG Grasp Infantil Vermelha e Azul',                             'categoria' => 'times-europeus',  'preco' => 29.9,  'precoAntes' => 89.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_psg_grasp_infantil_vermelha_e_azul_121813_1_5c55e50f57d4befd01688a947cd19fa6.jpg'],
            ['id' => 98,  'nome' => 'Camisa PSG Advance Infantil Azul',                                      'categoria' => 'times-europeus',  'preco' => 34.9,  'precoAntes' => 99.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_psg_advance_infantil_azul_121808_1_5795ad9ca1fe737d3c3d8a2944c0704b.jpg'],
            ['id' => 99,  'nome' => 'Calção PSG Apps Infantil Azul Marinho',                                 'categoria' => 'times-europeus',  'preco' => 54.9,  'precoAntes' => 99.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_calcao_psg_apps_infantil_azul_marinho_122150_1_b30c8939741515892de6e897e84bce24.jpg'],
            ['id' => 100, 'nome' => 'Kit de Camisa e Calção PSG Infantil Branca e Marinho',                  'categoria' => 'times-europeus',  'preco' => 109.8, 'precoAntes' => 189.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_de_camisa_e_calcao_psg_infantil_branca_e_marinho_140035_1_6479456157534f7b46f5a6fd632e5a89.jpg'],
            ['id' => 101, 'nome' => 'Kit Camisa e Calção PSG Infantil Azul',                                 'categoria' => 'times-europeus',  'preco' => 99.9,  'precoAntes' => 189.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_camisa_e_calcao_psg_infantil_azul_140033_1_381b5e223e770f54407e0576eafce51f.jpg'],
            ['id' => 102, 'nome' => 'Kit PSG Mini Craque Infantil Azul Marinho',                             'categoria' => 'times-europeus',  'preco' => 129.9, 'precoAntes' => 169.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_psg_mini_craque_infantil_azul_marinho_123687_1_67d720eb7e10f143b13623ec8b37c323.jpg'],
            ['id' => 103, 'nome' => 'Mochila PSG G Azul',                                                    'categoria' => 'times-europeus',  'preco' => 74.9,  'precoAntes' => 179.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_mochila_psg_g_azul_1_20251021135507_a393f90c5b91.jpg'],
            ['id' => 104, 'nome' => 'Mochila PSG Azul',                                                      'categoria' => 'times-europeus',  'preco' => 54.9,  'precoAntes' => 199.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_mochila_psg_azul_130822_1_a338ab7fe0cf2832a92a10c4f0ae5ea1.jpg'],
            ['id' => 105, 'nome' => 'Mochila com Rodas PSG Plus Stripes Azul',                               'categoria' => 'times-europeus',  'preco' => 129.9, 'precoAntes' => 489.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_mochila_com_rodas_psg_plus_stripes_azul_1_20251027154452_b4cb1cf336a1.jpg'],
            ['id' => 106, 'nome' => 'Mochila Com Rodas PSG Azul Claro',                                      'categoria' => 'times-europeus',  'preco' => 149.9, 'precoAntes' => 229.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_mochila_com_rodas_psg_azul_claro_127230_1_64f93239f2f15a0b246d704480e52ac9.jpg'],
            ['id' => 107, 'nome' => 'Kit Mochila + Lancheira PSG Azul',                                      'categoria' => 'times-europeus',  'preco' => 159.9, 'precoAntes' => 299.8,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_kit_mochila_lancheira_psg_azul_131628_1_0cff86fa67349993bce2f384bfe0e22f.jpg'],
            ['id' => 108, 'nome' => 'Lancheira PSG Colorida',                                                'categoria' => 'times-europeus',  'preco' => 29.9,  'precoAntes' => 99.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_lancheira_psg_colorida_130821_1_963eb401c07d9a96629364e8e48e4080.jpg'],
            ['id' => 109, 'nome' => 'Porta Chuteira PSG Y01 Preto',                                          'categoria' => 'times-europeus',  'preco' => 179.9, 'precoAntes' => 439.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_porta_chuteira_psg_y01_preto_1_20251021105605_8d9acaa06fb7.jpg'],
            // Atlético Madrid + Chelsea
            ['id' => 110, 'nome' => 'Moletom Canguru Atlético Madrid Azul',                                  'categoria' => 'times-europeus',  'preco' => 109.9, 'precoAntes' => 269.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_moletom_canguru_atletico_madrid_azul_118261_1_d1f7a318525a458be67da911674602a2.jpg'],
            ['id' => 111, 'nome' => 'Camisa Chelsea João Pedro N° 20 Juvenil Azul',                          'categoria' => 'times-europeus',  'preco' => 39.9,  'precoAntes' => 59.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_camisa_chelsea_joo_pedro_n_20_juvenil_azul_2_20260323080203_f029e3842349.jpg'],

            // ── CHUTEIRAS ───────────────────────────────────────────────────────────
            ['id' => 112, 'nome' => 'Chuteira Adidas F50 Club Society Amarela',                              'categoria' => 'chuteiras',        'preco' => 449.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_adidas_f50_club_society_amarela_1_20260114150757_74ab097fbfd2.jpg'],
            ['id' => 113, 'nome' => 'Chuteira Adidas F50 League FG/MG Campo Juvenil Verde',                  'categoria' => 'chuteiras',        'preco' => 499.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_adidas_f50_league_fgmg_campo_juvenil_verd_1_20260109133028_7264e86c4cf4.jpg'],
            ['id' => 114, 'nome' => 'Chuteira Adidas F50 Sparkfusion Club Society Feminina Branca e Verde',  'categoria' => 'chuteiras',        'preco' => 449.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_adidas_f50_sparkfusion_club_society_femin_1_20260115102928_9e748a94275d.jpg'],
            ['id' => 115, 'nome' => 'Chuteira Adidas F50 Sparkfusion Campo Feminina Branca',                 'categoria' => 'chuteiras',        'preco' => 699.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_adidas_f50_sparkfusion_campo_feminina_bra_1_20260114092259_897ebe6af205.jpg'],
            ['id' => 116, 'nome' => 'Chuteira Adidas Predator Club Campo Vermelha',                          'categoria' => 'chuteiras',        'preco' => 449.9, 'precoAntes' => 419.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_adidas_predator_club_campo_vermelha_1_20260113112330_5061dd5ef972.jpg'],
            ['id' => 117, 'nome' => 'Chuteira Adidas F50 Club Society Juvenil Amarelo',                      'categoria' => 'chuteiras',        'preco' => 379.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_adidas_f50_club_society_juvenil_amarelo_1_20260102095753_006e1db74c0e.jpg'],
            ['id' => 118, 'nome' => 'Chuteira Adidas F50 League Campo Verde Limão',                          'categoria' => 'chuteiras',        'preco' => 699.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_adidas_f50_league_campo_verde_limo_1_20260102103225_69b330c4482c.jpg'],
            ['id' => 119, 'nome' => 'Chuteira Adidas F50 League Laceless Campo Lamine Yamal Roxa e Rosa',    'categoria' => 'chuteiras',        'preco' => 699.9,                          'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_adidas_f50_league_laceless_campo_lamine_y_1_20251230163521_9be614600667.jpg'],
            ['id' => 120, 'nome' => 'Chuteira Joma Top Flex Society Preto',                                  'categoria' => 'chuteiras',        'preco' => 649.9, 'precoAntes' => 469.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_joma_top_flex_society_preto_1_20251016151940_674f904fb8f7.jpg'],
            ['id' => 121, 'nome' => 'Chuteira Joma Invicto Futsal Verde',                                    'categoria' => 'chuteiras',        'preco' => 749.9, 'precoAntes' => 369.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_joma_invicto_futsal_verde_1_20251017100206_8be4320779b4.jpg'],
            ['id' => 122, 'nome' => 'Chuteira Joma Aguila Society Azul',                                     'categoria' => 'chuteiras',        'preco' => 399.9, 'precoAntes' => 229.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_joma_aguila_society_azul_1_20251017082706_5604c012b8e3.jpg'],
            ['id' => 123, 'nome' => 'Chuteira Joma Aguila Cup Firm Grounds Campo Branca e Preta',            'categoria' => 'chuteiras',        'preco' => 599.9, 'precoAntes' => 399.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_joma_aguila_cup_firm_grounds_branco_e_pre_1_20251017104844_c9cc799397fe.jpg'],
            ['id' => 124, 'nome' => 'Chuteira Penalty Matis XXI Society Juvenil Marinho',                    'categoria' => 'chuteiras',        'preco' => 179.9, 'precoAntes' => 129.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_matis_xxi_society_juvenil_marinho_1_20251022162701_ebb19f72ad8f.jpg'],
            ['id' => 125, 'nome' => 'Chuteira Penalty Matis Futsal Juvenil Turquesa',                        'categoria' => 'chuteiras',        'preco' => 159.9, 'precoAntes' => 139.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_matis_futsal_juvenil_turquesa_1_20251023082650_f6e910b54adc.jpg'],
            ['id' => 126, 'nome' => 'Chuteira Penalty Garra Futsal Branca e Marinho',                        'categoria' => 'chuteiras',        'preco' => 179.9, 'precoAntes' => 159.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_garra_futsal_branca_e_marinho_1_20251022075108_857464153245.jpg'],
            ['id' => 127, 'nome' => 'Chuteira Penalty Furia Futsal Azul e Branca',                           'categoria' => 'chuteiras',        'preco' => 229.9, 'precoAntes' => 139.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_furia_futsal_azul_e_branca_1_20251022080930_61c8fe718607.jpg'],
            ['id' => 128, 'nome' => 'Chuteira Penalty S11 Locker XXI Campo Branca',                          'categoria' => 'chuteiras',        'preco' => 279.9, 'precoAntes' => 179.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_s11_locker_xxi_campo_branca_1_20251017111937_da93513bc348.jpg'],
            ['id' => 129, 'nome' => 'Chuteira Penalty Brasil 70 Futsal Azul e Preta',                        'categoria' => 'chuteiras',        'preco' => 299.9, 'precoAntes' => 219.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_brasil_70_futsal_azul_e_preta_1_20251022165216_eddcfb6184e9.jpg'],
            ['id' => 130, 'nome' => 'Chuteira Penalty Se7e Locker Society Marinho',                          'categoria' => 'chuteiras',        'preco' => 379.9, 'precoAntes' => 319.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_se7e_locker_society_marinho_1_20251022105358_4042be42b9b5.jpg'],
            ['id' => 131, 'nome' => 'Chuteira Penalty Se7e Locker Society Preta',                            'categoria' => 'chuteiras',        'preco' => 379.9, 'precoAntes' => 299.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_se7e_locker_society_preta_1_20251022170826_0413fdd34308.jpg'],
            ['id' => 132, 'nome' => 'Chuteira Penalty S11 Locker Ecoknit XXI Campo Preta e Branca',          'categoria' => 'chuteiras',        'preco' => 449.9, 'precoAntes' => 299.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_s11_locker_ecoknit_xxi_campo_preta_e_branca_1_20251023102848_5be1e6d87859.jpg'],
            ['id' => 133, 'nome' => 'Chuteira Penalty Se7e Pro Molix Locker Society Cinza e Preta',          'categoria' => 'chuteiras',        'preco' => 449.9, 'precoAntes' => 279.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_se7e_pro_molix_locker_society_cinza_e_preta_1_20251022171652_0fc7dfe18a7b.jpg'],
            ['id' => 134, 'nome' => 'Chuteira Penalty Se7e Pro Molix Locker Society Preta e Roxa',           'categoria' => 'chuteiras',        'preco' => 449.9, 'precoAntes' => 259.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_penalty_se7e_pro_molix_locker_society_preta_e_roxa_1_20251023081027_3ddd4dc0f402.jpg'],
            ['id' => 135, 'nome' => 'Chuteira Skechers Youth JR Society Roxa Infantil',                      'categoria' => 'chuteiras',        'preco' => 299.9, 'precoAntes' => 199.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_chuteira_skechers_youth_jr_society_roxa_infantil_1_20250926135513_70753d072cd6.jpg'],

            // ── BONÉS E GORROS ──────────────────────────────────────────────────────
            ['id' => 136, 'nome' => 'Boné Manchester City Escudo 6 Gomos Marinho',                           'categoria' => 'bones-e-gorros',   'preco' => 49.9,  'precoAntes' => 129.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_manchester_city_escudo_6_gomos_marinho_1_20260420080003_0a5dea79bcd0.jpg'],
            ['id' => 137, 'nome' => 'Boné Manchester City Trucker Azul',                                     'categoria' => 'bones-e-gorros',   'preco' => 49.9,  'precoAntes' => 109.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_manchester_city_trucker_azul_1_20260420074601_654f717a1c1e.jpg'],
            ['id' => 138, 'nome' => 'Boné Barcelona Dad 6 Gomos Vermelho',                                   'categoria' => 'bones-e-gorros',   'preco' => 49.9,  'precoAntes' => 99.9,   'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_barcelona_dad_6_gomos_vermelho_1_20260417124754_3e69935640d3.jpg'],
            ['id' => 139, 'nome' => 'Boné Barcelona Americano Azul e Vermelho',                              'categoria' => 'bones-e-gorros',   'preco' => 49.9,  'precoAntes' => 119.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_barcelona_americano_azul_e_vermelho_1_20260417113835_120678b13a10.jpg'],
            ['id' => 140, 'nome' => 'Boné Barcelona Trucker Americano Vermelho e Azul',                      'categoria' => 'bones-e-gorros',   'preco' => 49.9,  'precoAntes' => 119.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_barcelona_trucker_americano_vermelho_e_azul_1_20260420082012_7d2660642e18.jpg'],
            ['id' => 141, 'nome' => 'Boné Barcelona Trucker Americano Azul e Vermelho',                      'categoria' => 'bones-e-gorros',   'preco' => 49.9,  'precoAntes' => 119.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_barcelona_trucker_americano_azul_e_vermelho_1_20260420081041_559f45574433.jpg'],
            ['id' => 142, 'nome' => 'Boné New Era Los Angeles Dodgers MLB Trucker WSHD 940AF Preto',         'categoria' => 'bones-e-gorros',   'preco' => 249.9, 'precoAntes' => 349.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_los_angeles_dodgers_mlb_trucker_wshd_1_20260415114731_069638e1c340.jpg'],
            ['id' => 143, 'nome' => 'Boné New Era New York Yankees MLB Trucker WSHD 940AF Preto',            'categoria' => 'bones-e-gorros',   'preco' => 249.9, 'precoAntes' => 349.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_new_york_yankees_mlb_trucker_wshd_940a_1_20260415113456_87ce31f208ce.jpg'],
            ['id' => 144, 'nome' => 'Boné New Era NY Yankees MLB World Series 2000 5950 Marinho',            'categoria' => 'bones-e-gorros',   'preco' => 189.9, 'precoAntes' => 249.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_new_york_yankees_mlb_world_series_2000_1_20260413163139_aff1fc184a52.jpg'],
            ['id' => 145, 'nome' => 'Boné New Era San Francisco 49ers NFL Historic Off White',               'categoria' => 'bones-e-gorros',   'preco' => 169.9, 'precoAntes' => 279.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_san_francisco_49ers_nfl_historic_bran_1_20260417141451_e6418a0b406f.jpg'],
            ['id' => 146, 'nome' => 'Boné New Era Philadelphia Eagles NFL 3930 AF Off White',               'categoria' => 'bones-e-gorros',   'preco' => 169.9, 'precoAntes' => 279.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_philadelphia_eagles_nfl_3930_af_off_w_1_20260417120353_0e88380e26af.jpg'],
            ['id' => 147, 'nome' => 'Boné New Era NY Yankees MLB Snap 940 Cinza',                            'categoria' => 'bones-e-gorros',   'preco' => 169.9, 'precoAntes' => 279.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_new_york_yankees_mlb_snap_940_cinza_1_20260417110236_002dc48ab983.jpg'],
            ['id' => 148, 'nome' => 'Boné New Era Miami Dolphins NFL Q126 3930 AF Off White',               'categoria' => 'bones-e-gorros',   'preco' => 169.9, 'precoAntes' => 279.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_miami_dolphins_nfl_q126_3930_af_off_white_1_20260417155606_290a237c50b8.jpg'],
            ['id' => 149, 'nome' => 'Boné New Era Los Angeles Rams NFL Q126 3930 AF Off White',             'categoria' => 'bones-e-gorros',   'preco' => 169.9, 'precoAntes' => 279.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_los_angeles_rams_nfl_q126_3930_af_off_1_20260417121748_47ef9687312c.jpg'],
            ['id' => 150, 'nome' => 'Boné New Era NY Yankees MLB Metallic Pin 940',                          'categoria' => 'bones-e-gorros',   'preco' => 129.9, 'precoAntes' => 219.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_new_york_yankees_mlb_mettalic_pin_940_1_20260417081106_204562b1f5a1.jpg'],
            ['id' => 151, 'nome' => 'Boné New Era NY Yankees MLB MVP 940 M-Crown',                           'categoria' => 'bones-e-gorros',   'preco' => 139.9, 'precoAntes' => 229.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_new_york_yankees_mlb_mvp_940_m_crown_1_20260417152351_7bb8f519fe53.jpg'],
            ['id' => 152, 'nome' => 'Boné New Era NY Yankees MLB Recycled Midi 94',                          'categoria' => 'bones-e-gorros',   'preco' => 139.9, 'precoAntes' => 229.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_new_york_yankees_mlb_recycled_midi_94_1_20260417090544_9db8c6b90672.jpg'],
            ['id' => 153, 'nome' => 'Boné New Era NY Yankees MLB 3930 M-Crown Marinho',                      'categoria' => 'bones-e-gorros',   'preco' => 179.9, 'precoAntes' => 299.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_new_york_yankees_mlb_3930_m_crown_mari_1_20260417111702_135767f24d25.jpg'],
            ['id' => 154, 'nome' => 'Boné New Era NY Yankees MLB Fitted 3930 M-Crown Marinho',               'categoria' => 'bones-e-gorros',   'preco' => 179.9, 'precoAntes' => 299.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_new_york_yankees_mlb_fitted_3930_m_cro_1_20260417115011_fdab703f1a70.jpg'],
            ['id' => 155, 'nome' => 'Boné New Era NY Yankees MLB Mythical Eframe 940 AF Bege',               'categoria' => 'bones-e-gorros',   'preco' => 139.9, 'precoAntes' => 229.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_new_york_yankees_mlb_mythical_eframe_9_1_20260417144233_e6d7c03de2f6.jpg'],
            ['id' => 156, 'nome' => 'Boné New Era NY Yankees MLB Stretch 970 Marinho',                       'categoria' => 'bones-e-gorros',   'preco' => 169.9, 'precoAntes' => 279.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_new_york_yankees_mlb_stretch_970_marin_1_20260417104429_66ce42addcc3.jpg'],
            ['id' => 157, 'nome' => 'Boné New Era LA Lakers NBA Microfiber 940 Preto',                       'categoria' => 'bones-e-gorros',   'preco' => 129.9, 'precoAntes' => 219.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_los_angeles_lakers_nba_microfober_940_1_20260417100657_fb40cf5adbba.jpg'],
            ['id' => 158, 'nome' => 'Boné New Era LA Dodgers MLB Mythical Bege e Azul',                      'categoria' => 'bones-e-gorros',   'preco' => 139.9, 'precoAntes' => 229.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_los_angeles_dodgers_mlb_mythical_bege_1_20260417151143_88ad2d83223e.jpg'],
            ['id' => 159, 'nome' => 'Boné New Era LA Dodgers MLB Stretch 3930 M-Crown Preto',                'categoria' => 'bones-e-gorros',   'preco' => 179.9, 'precoAntes' => 299.9,  'imagem' => 'https://images.tcdn.com.br/img/img_prod/311840/180_bon_new_era_los_angeles_dodgers_mlb_stretch_3930_m_crown_preto_1_20260417142907_43cde3886115.jpg'],
        ];
    }
}
