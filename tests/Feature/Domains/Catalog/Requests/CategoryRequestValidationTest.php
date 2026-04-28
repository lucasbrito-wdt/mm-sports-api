<?php

use App\Domains\Catalog\Models\Category;
use App\Domains\Catalog\Requests\Admin\StoreCategoryRequest;
use App\Domains\Catalog\Requests\Admin\UpdateCategoryRequest;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

function categoriesRequestMigrationInstance(): object
{
    /** @var object $migration */
    $migration = require base_path('app/Domains/Catalog/Migrations/2026_04_25_141800_create_categories_and_link_products.php');

    return $migration;
}

beforeEach(function () {
    if (! Schema::hasTable('categories') || ! Schema::hasColumn('products', 'category_id')) {
        $migration = categoriesRequestMigrationInstance();
        call_user_func([$migration, 'up']);
    }
});

it('valida slug duplicado no store', function () {
    Category::create([
        'name' => 'Roupas',
        'slug' => 'roupas',
    ]);

    $request = new StoreCategoryRequest;
    $validator = Validator::make([
        'name' => 'Roupas 2',
        'slug' => 'roupas',
    ], $request->store());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('slug'))->toBeTrue();
});

it('valida parent_id inválido no store', function () {
    $request = new StoreCategoryRequest;
    $validator = Validator::make([
        'name' => 'Acessórios',
        'slug' => 'acessorios',
        'parent_id' => 'invalido',
    ], $request->store());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('parent_id'))->toBeTrue();
});

it('valida parent_id inexistente no store', function () {
    $request = new StoreCategoryRequest;
    $validator = Validator::make([
        'name' => 'Acessórios',
        'slug' => 'acessorios',
        'parent_id' => (string) Str::ulid(),
    ], $request->store());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('parent_id'))->toBeTrue();
});

it('valida slug duplicado no update', function () {
    $existing = Category::create([
        'name' => 'Futebol',
        'slug' => 'futebol',
    ]);

    $category = Category::create([
        'name' => 'Corrida',
        'slug' => 'corrida',
    ]);

    $request = new class($category->id) extends UpdateCategoryRequest
    {
        public function __construct(private readonly string $categoryId) {}

        public function route($param = null, $default = null): mixed
        {
            if ($param === 'category' || $param === 'id') {
                return $this->categoryId;
            }

            return $default;
        }
    };

    $validator = Validator::make([
        'slug' => $existing->slug,
    ], $request->update());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('slug'))->toBeTrue();
});

it('valida auto-parent no update', function () {
    $category = Category::create([
        'name' => 'Moda',
        'slug' => 'moda',
    ]);

    $request = new class($category->id) extends UpdateCategoryRequest
    {
        public function __construct(private readonly string $categoryId) {}

        public function route($param = null, $default = null): mixed
        {
            if ($param === 'category' || $param === 'id') {
                return $this->categoryId;
            }

            return $default;
        }
    };

    $validator = Validator::make([
        'parent_id' => $category->id,
    ], $request->update());

    expect($validator->fails())->toBeTrue()
        ->and($validator->errors()->has('parent_id'))->toBeTrue();
});
