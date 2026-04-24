<?php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Enums\ProductOrigin;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\GenerateVariantMatrixService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

uses(TestCase::class, RefreshDatabase::class);

beforeEach(function () {
    $this->color = Attribute::create([
        'code' => 'color', 'label' => 'Cor',
        'type' => AttributeType::Variant,
        'input_type' => AttributeInputType::Swatch,
    ]);
    $this->size = Attribute::create([
        'code' => 'size', 'label' => 'Tamanho',
        'type' => AttributeType::Variant,
        'input_type' => AttributeInputType::Select,
    ]);
    $this->azul = AttributeValue::create(['attribute_id' => $this->color->id, 'value' => 'Azul', 'slug' => 'azul']);
    $this->vermelho = AttributeValue::create(['attribute_id' => $this->color->id, 'value' => 'Vermelho', 'slug' => 'vermelho']);
    $this->p = AttributeValue::create(['attribute_id' => $this->size->id, 'value' => 'P', 'slug' => 'p']);
    $this->m = AttributeValue::create(['attribute_id' => $this->size->id, 'value' => 'M', 'slug' => 'm']);
    $this->g = AttributeValue::create(['attribute_id' => $this->size->id, 'value' => 'G', 'slug' => 'g']);

    $this->product = Product::create([
        'title' => 'Camisa',
        'slug' => 'camisa-matriz',
        'origin' => ProductOrigin::National,
        'status' => ProductStatus::Published,
    ]);
});

it('generates 2x3 matrix (6 SKUs) idempotently', function () {
    $service = app(GenerateVariantMatrixService::class);

    $service->handle($this->product, [
        $this->color->id => [$this->azul->id, $this->vermelho->id],
        $this->size->id => [$this->p->id, $this->m->id, $this->g->id],
    ]);

    expect($this->product->variants()->count())->toBe(6);

    $service->handle($this->product, [
        $this->color->id => [$this->azul->id, $this->vermelho->id],
        $this->size->id => [$this->p->id, $this->m->id, $this->g->id],
    ]);

    expect($this->product->variants()->count())->toBe(6);
});

it('populates attribute_payload and attribute_value_ids on each variant', function () {
    $service = app(GenerateVariantMatrixService::class);

    $service->handle($this->product, [
        $this->color->id => [$this->azul->id],
        $this->size->id => [$this->m->id],
    ]);

    $variant = $this->product->variants()->first();

    expect($variant->attribute_payload)->toBe(['color' => 'Azul', 'size' => 'M']);
    expect($variant->attribute_value_ids)->toEqualCanonicalizing([$this->azul->id, $this->m->id]);
});
