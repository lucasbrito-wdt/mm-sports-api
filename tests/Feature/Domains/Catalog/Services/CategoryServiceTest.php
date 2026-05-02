<?php

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\CategoryService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

function categoriesServiceMigrationInstance(): object
{
    /** @var object $migration */
    $migration = require base_path('app/Domains/Catalog/Migrations/2026_04_25_141800_create_categories_and_link_products.php');

    return $migration;
}

beforeEach(function () {
    if (! Schema::hasTable('categories') || ! Schema::hasColumn('products', 'category_id')) {
        $migration = categoriesServiceMigrationInstance();
        call_user_func([$migration, 'up']);
    }
});

it('impede parent_id igual ao próprio id no update', function () {
    $category = Category::create([
        'name' => 'Roupas',
        'slug' => 'roupas',
    ]);

    expect(fn () => app(CategoryService::class)->update(['parent_id' => $category->id], $category->id))
        ->toThrow(ValidationException::class, 'A categoria não pode ser filha dela mesma.');
});

it('impede ciclo quando tenta definir descendente como pai', function () {
    $root = Category::create([
        'name' => 'Roupas',
        'slug' => 'roupas',
    ]);

    $child = Category::create([
        'name' => 'Camisetas',
        'slug' => 'camisetas',
        'parent_id' => $root->id,
    ]);

    $grandChild = Category::create([
        'name' => 'Camisetas Dry',
        'slug' => 'camisetas-dry',
        'parent_id' => $child->id,
    ]);

    expect(fn () => app(CategoryService::class)->update(['parent_id' => $grandChild->id], $root->id))
        ->toThrow(ValidationException::class, 'Não é permitido criar ciclo na hierarquia de categorias.');
});

it('bloqueia remoção quando existem subcategorias vinculadas', function () {
    $parent = Category::create([
        'name' => 'Calçados',
        'slug' => 'calcados',
    ]);

    Category::create([
        'name' => 'Tênis',
        'slug' => 'tenis',
        'parent_id' => $parent->id,
    ]);

    expect(fn () => app(CategoryService::class)->delete($parent->id))
        ->toThrow(ValidationException::class, 'Não é possível remover a categoria porque existem subcategorias vinculadas.');
});

it('bloqueia remoção quando existem produtos vinculados', function () {
    $category = Category::create([
        'name' => 'Acessórios',
        'slug' => 'acessorios',
    ]);

    Product::create([
        'title' => 'Boné',
        'slug' => 'bone-01',
        'origin' => 'national',
        'status' => 'draft',
        'category_id' => $category->id,
    ]);

    expect(fn () => app(CategoryService::class)->delete($category->id))
        ->toThrow(ValidationException::class, 'Não é possível remover a categoria porque existem produtos vinculados.');
});

it('retorna descendentes em todos os níveis', function () {
    $root = Category::create([
        'name' => 'Esportes',
        'slug' => 'esportes',
    ]);
    $childA = Category::create([
        'name' => 'Futebol',
        'slug' => 'futebol',
        'parent_id' => $root->id,
    ]);
    $childB = Category::create([
        'name' => 'Corrida',
        'slug' => 'corrida',
        'parent_id' => $root->id,
    ]);
    $grandChild = Category::create([
        'name' => 'Chuteiras',
        'slug' => 'chuteiras',
        'parent_id' => $childA->id,
    ]);

    $ids = app(CategoryService::class)->getDescendantIds($root->id);

    expect($ids)->toHaveCount(3)
        ->and($ids)->toContain($childA->id, $childB->id, $grandChild->id);
});

it('não entra em loop ao buscar descendentes com dados cíclicos anômalos', function () {
    $categoryA = Category::create([
        'name' => 'A',
        'slug' => 'a',
    ]);

    $categoryB = Category::create([
        'name' => 'B',
        'slug' => 'b',
        'parent_id' => $categoryA->id,
    ]);

    DB::table('categories')
        ->where('id', $categoryA->id)
        ->update(['parent_id' => $categoryB->id]);

    $ids = app(CategoryService::class)->getDescendantIds($categoryA->id);

    expect($ids)->toBe([$categoryB->id]);
});

it('monta árvore hierárquica de categorias', function () {
    $root = Category::create([
        'name' => 'Moda',
        'slug' => 'moda',
        'display_order' => 1,
    ]);
    $child = Category::create([
        'name' => 'Feminino',
        'slug' => 'feminino',
        'parent_id' => $root->id,
        'display_order' => 1,
    ]);

    Category::create([
        'name' => 'Vestidos',
        'slug' => 'vestidos',
        'parent_id' => $child->id,
        'display_order' => 1,
    ]);

    $tree = app(CategoryService::class)->buildTree();

    expect($tree)->toHaveCount(1)
        ->and($tree[0]['id'])->toBe($root->id)
        ->and($tree[0]['children'])->toHaveCount(1)
        ->and($tree[0]['children'][0]['id'])->toBe($child->id)
        ->and($tree[0]['children'][0]['children'])->toHaveCount(1);
});
