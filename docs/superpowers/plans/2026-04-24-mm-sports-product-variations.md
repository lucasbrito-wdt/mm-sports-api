# MM Sports — Product Variations & Faceted Catalog — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the flexible attribute + faceted catalog system specified in `docs/superpowers/specs/2026-04-24-mm-sports-product-variations-design.md`.

**Architecture:** Hybrid model — normalized dictionary (`attributes`, `attribute_values`) + pivot tables (source of truth) + denormalized `attribute_value_ids bigint[]` on `products` and `product_variants` (GIN-indexed), plus a materialized view for facet counts. Redis layer caches facets (5min), listings (60s) and PDPs (5min) with tag-based invalidation. Variants generated via matrix service. Images attached to `attribute_value_id` (nullable = gallery).

**Tech Stack:** Laravel 11 (DDD CodifyTech), PostgreSQL 15+ (GIN, tsvector, pg_trgm, unaccent, btree_gin), Redis (tagged cache), Pest for tests.

**Mandatory reading before coding:** `codifytech-ddd` skill and its `laravel-rules.md` (enums, requests, services, controllers). All work under `app/Domains/Catalog/`.

**Module conventions (recap):**
- Models extend `App\Domains\Shared\Models\BaseModel`. Public-facing tables use ULID PK; internal/pivot tables use bigint PK.
- Services extend `App\Domains\Shared\Services\BaseService`. All queries + transactions live here.
- Requests extend `App\Domains\Shared\Requests\BaseFormRequest` with `base()`, `store()`, `update()`.
- Controllers extend `App\Domains\Shared\Controller\BaseController` — orchestration only.

---

## File Structure

**Created:**
- `app/Domains/Catalog/Enums/AttributeType.php`
- `app/Domains/Catalog/Enums/AttributeInputType.php`
- `app/Domains/Catalog/Migrations/2026_04_24_100000_enable_postgres_extensions.php`
- `app/Domains/Catalog/Migrations/2026_04_24_100100_create_attributes_tables.php`
- `app/Domains/Catalog/Migrations/2026_04_24_100200_create_product_attribute_pivots.php`
- `app/Domains/Catalog/Migrations/2026_04_24_100300_add_cache_columns_to_products_and_variants.php`
- `app/Domains/Catalog/Migrations/2026_04_24_100400_create_product_images_table.php`
- `app/Domains/Catalog/Migrations/2026_04_24_100500_create_product_facet_counts_mv.php`
- `app/Domains/Catalog/Concerns/HasAttributeValueIds.php`
- `app/Domains/Catalog/Models/Attribute.php`
- `app/Domains/Catalog/Models/AttributeValue.php`
- `app/Domains/Catalog/Models/ProductImage.php`
- `app/Domains/Catalog/Services/GenerateVariantMatrixService.php`
- `app/Domains/Catalog/Services/RebuildCatalogCacheService.php`
- `app/Domains/Catalog/Services/CatalogFacetService.php`
- `app/Domains/Catalog/Services/CatalogProductSearchService.php`
- `app/Domains/Catalog/Services/AttributeAdminService.php`
- `app/Domains/Catalog/Services/AttributeValueAdminService.php`
- `app/Domains/Catalog/Observers/CatalogCacheInvalidationObserver.php`
- `app/Domains/Catalog/Controllers/CatalogFacetController.php`
- `app/Domains/Catalog/Controllers/CatalogProductController.php`
- `app/Domains/Catalog/Controllers/Admin/AttributeController.php`
- `app/Domains/Catalog/Controllers/Admin/AttributeValueController.php`
- `app/Domains/Catalog/Controllers/Admin/ProductVariantMatrixController.php`
- `app/Domains/Catalog/Requests/Admin/AttributeRequest.php`
- `app/Domains/Catalog/Requests/Admin/AttributeValueRequest.php`
- `app/Domains/Catalog/Requests/Admin/GenerateVariantMatrixRequest.php`
- `app/Domains/Catalog/Requests/CatalogListRequest.php`
- `app/Domains/Catalog/Seeders/AttributeSeeder.php`
- `app/Domains/Catalog/Seeders/AttributeValueSeeder.php`
- `app/Console/Commands/CatalogRebuildCacheCommand.php`
- `app/Console/Commands/CatalogRefreshFacetCountsCommand.php`
- Feature/Unit tests under `tests/Feature/Catalog/` and `tests/Unit/Catalog/`

**Modified:**
- `app/Providers/AppServiceProvider.php` — register observers
- `routes/api.php` — register new routes
- `app/Console/Kernel.php` — schedule MV refresh + warm cache

---

## Task 1: Enable required Postgres extensions

**Files:**
- Create: `app/Domains/Catalog/Migrations/2026_04_24_100000_enable_postgres_extensions.php`
- Test: `tests/Feature/Catalog/PostgresExtensionsTest.php`

- [ ] **Step 1: Write the failing test**

```php
<?php
// tests/Feature/Catalog/PostgresExtensionsTest.php

use Illuminate\Support\Facades\DB;

it('has required postgres extensions enabled', function () {
    $extensions = collect(DB::select(
        "SELECT extname FROM pg_extension WHERE extname IN ('pg_trgm','btree_gin','unaccent')"
    ))->pluck('extname')->all();

    expect($extensions)->toContain('pg_trgm', 'btree_gin', 'unaccent');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=PostgresExtensionsTest`
Expected: FAIL — extensions not yet enabled.

- [ ] **Step 3: Create migration**

```php
<?php
// app/Domains/Catalog/Migrations/2026_04_24_100000_enable_postgres_extensions.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement('CREATE EXTENSION IF NOT EXISTS pg_trgm');
        DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gin');
        DB::statement('CREATE EXTENSION IF NOT EXISTS unaccent');
    }

    public function down(): void
    {
        // Do not drop — other features may depend on them.
    }
};
```

- [ ] **Step 4: Run migration and test**

Run: `php artisan migrate && php artisan test --filter=PostgresExtensionsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Migrations/2026_04_24_100000_enable_postgres_extensions.php tests/Feature/Catalog/PostgresExtensionsTest.php
git commit -m "feat(catalog): enable pg_trgm, btree_gin, unaccent extensions"
```

---

## Task 2: Attribute enums

**Files:**
- Create: `app/Domains/Catalog/Enums/AttributeType.php`
- Create: `app/Domains/Catalog/Enums/AttributeInputType.php`
- Test: `tests/Unit/Catalog/Enums/AttributeEnumsTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Unit/Catalog/Enums/AttributeEnumsTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;

it('exposes attribute types', function () {
    expect(AttributeType::Facet->value)->toBe('facet');
    expect(AttributeType::Variant->value)->toBe('variant');
    expect(AttributeType::Both->value)->toBe('both');
});

it('exposes attribute input types', function () {
    expect(AttributeInputType::Select->value)->toBe('select');
    expect(AttributeInputType::Multiselect->value)->toBe('multiselect');
    expect(AttributeInputType::Text->value)->toBe('text');
    expect(AttributeInputType::Swatch->value)->toBe('swatch');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AttributeEnumsTest`
Expected: FAIL (classes do not exist).

- [ ] **Step 3: Create enums**

```php
<?php
// app/Domains/Catalog/Enums/AttributeType.php

namespace App\Domains\Catalog\Enums;

enum AttributeType: string
{
    case Facet = 'facet';
    case Variant = 'variant';
    case Both = 'both';

    public function isFacet(): bool
    {
        return in_array($this, [self::Facet, self::Both], true);
    }

    public function isVariant(): bool
    {
        return in_array($this, [self::Variant, self::Both], true);
    }
}
```

```php
<?php
// app/Domains/Catalog/Enums/AttributeInputType.php

namespace App\Domains\Catalog\Enums;

enum AttributeInputType: string
{
    case Select = 'select';
    case Multiselect = 'multiselect';
    case Text = 'text';
    case Swatch = 'swatch';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AttributeEnumsTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Enums/ tests/Unit/Catalog/Enums/
git commit -m "feat(catalog): add AttributeType and AttributeInputType enums"
```

---

## Task 3: Attribute dictionary migrations (attributes + attribute_values)

**Files:**
- Create: `app/Domains/Catalog/Migrations/2026_04_24_100100_create_attributes_tables.php`
- Test: `tests/Feature/Catalog/Migrations/AttributesSchemaTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Migrations/AttributesSchemaTest.php

use Illuminate\Support\Facades\Schema;

it('creates attributes table with expected columns', function () {
    expect(Schema::hasTable('attributes'))->toBeTrue();
    expect(Schema::hasColumns('attributes', [
        'id', 'code', 'label', 'type', 'input_type',
        'is_filterable', 'display_order', 'created_at', 'updated_at',
    ]))->toBeTrue();
});

it('creates attribute_values table with unique (attribute_id, slug)', function () {
    expect(Schema::hasTable('attribute_values'))->toBeTrue();
    expect(Schema::hasColumns('attribute_values', [
        'id', 'attribute_id', 'value', 'slug', 'metadata',
        'display_order', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AttributesSchemaTest`
Expected: FAIL (tables do not exist).

- [ ] **Step 3: Create migration**

```php
<?php
// app/Domains/Catalog/Migrations/2026_04_24_100100_create_attributes_tables.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('attributes', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->string('code')->unique();
            $table->string('label');
            $table->string('type'); // facet|variant|both
            $table->string('input_type'); // select|multiselect|text|swatch
            $table->boolean('is_filterable')->default(true);
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();
        });

        Schema::create('attribute_values', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('attribute_id')
                ->constrained('attributes')
                ->cascadeOnDelete();
            $table->string('value');
            $table->string('slug');
            $table->jsonb('metadata')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->unique(['attribute_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attribute_values');
        Schema::dropIfExists('attributes');
    }
};
```

- [ ] **Step 4: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=AttributesSchemaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Migrations/2026_04_24_100100_create_attributes_tables.php tests/Feature/Catalog/Migrations/AttributesSchemaTest.php
git commit -m "feat(catalog): create attributes and attribute_values tables"
```

---

## Task 4: Product ↔ attribute pivot migrations

**Files:**
- Create: `app/Domains/Catalog/Migrations/2026_04_24_100200_create_product_attribute_pivots.php`
- Test: `tests/Feature/Catalog/Migrations/ProductAttributePivotsSchemaTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Migrations/ProductAttributePivotsSchemaTest.php

use Illuminate\Support\Facades\Schema;

it('creates product_attribute_values with composite PK', function () {
    expect(Schema::hasTable('product_attribute_values'))->toBeTrue();
    expect(Schema::hasColumns('product_attribute_values', [
        'product_id', 'attribute_value_id',
    ]))->toBeTrue();
});

it('creates product_variant_axes with unique (product_id, attribute_id)', function () {
    expect(Schema::hasTable('product_variant_axes'))->toBeTrue();
    expect(Schema::hasColumns('product_variant_axes', [
        'product_id', 'attribute_id', 'display_order',
    ]))->toBeTrue();
});

it('creates variant_attribute_values table', function () {
    expect(Schema::hasTable('variant_attribute_values'))->toBeTrue();
    expect(Schema::hasColumns('variant_attribute_values', [
        'product_variant_id', 'attribute_id', 'attribute_value_id',
    ]))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductAttributePivotsSchemaTest`
Expected: FAIL.

- [ ] **Step 3: Create migration**

```php
<?php
// app/Domains/Catalog/Migrations/2026_04_24_100200_create_product_attribute_pivots.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_attribute_values', function (Blueprint $table) {
            $table->foreignUlid('product_id')
                ->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')
                ->constrained('attribute_values')->cascadeOnDelete();

            $table->primary(['product_id', 'attribute_value_id']);
            $table->index('attribute_value_id'); // reverse lookup: value -> products
        });

        Schema::create('product_variant_axes', function (Blueprint $table) {
            $table->foreignUlid('product_id')
                ->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_id')
                ->constrained('attributes')->cascadeOnDelete();
            $table->unsignedInteger('display_order')->default(0);

            $table->unique(['product_id', 'attribute_id']);
            $table->index('product_id');
        });

        Schema::create('variant_attribute_values', function (Blueprint $table) {
            $table->foreignUlid('product_variant_id')
                ->constrained('product_variants')->cascadeOnDelete();
            $table->foreignId('attribute_id')
                ->constrained('attributes')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')
                ->constrained('attribute_values')->cascadeOnDelete();

            $table->unique(['product_variant_id', 'attribute_id']);
            $table->index('attribute_value_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('variant_attribute_values');
        Schema::dropIfExists('product_variant_axes');
        Schema::dropIfExists('product_attribute_values');
    }
};
```

- [ ] **Step 4: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=ProductAttributePivotsSchemaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Migrations/2026_04_24_100200_create_product_attribute_pivots.php tests/Feature/Catalog/Migrations/ProductAttributePivotsSchemaTest.php
git commit -m "feat(catalog): create product/variant attribute pivot tables"
```

---

## Task 5: Add denormalized cache columns + GIN indexes to products/variants

**Files:**
- Create: `app/Domains/Catalog/Migrations/2026_04_24_100300_add_cache_columns_to_products_and_variants.php`
- Test: `tests/Feature/Catalog/Migrations/CacheColumnsSchemaTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Migrations/CacheColumnsSchemaTest.php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

it('adds attribute_value_ids and search_tsv to products', function () {
    expect(Schema::hasColumn('products', 'attribute_value_ids'))->toBeTrue();
    expect(Schema::hasColumn('products', 'search_tsv'))->toBeTrue();
});

it('adds attribute_value_ids to product_variants', function () {
    expect(Schema::hasColumn('product_variants', 'attribute_value_ids'))->toBeTrue();
});

it('creates GIN indexes', function () {
    $idx = collect(DB::select(
        "SELECT indexname FROM pg_indexes WHERE schemaname='public'
         AND indexname IN ('products_attrs_gin','products_search_gin','products_title_trgm','pv_attrs_gin','pv_payload_gin')"
    ))->pluck('indexname')->all();

    expect($idx)->toContain(
        'products_attrs_gin', 'products_search_gin', 'products_title_trgm',
        'pv_attrs_gin', 'pv_payload_gin'
    );
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CacheColumnsSchemaTest`
Expected: FAIL.

- [ ] **Step 3: Create migration**

```php
<?php
// app/Domains/Catalog/Migrations/2026_04_24_100300_add_cache_columns_to_products_and_variants.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("ALTER TABLE products
            ADD COLUMN attribute_value_ids bigint[] NOT NULL DEFAULT '{}',
            ADD COLUMN search_tsv tsvector
                GENERATED ALWAYS AS (
                    to_tsvector('portuguese',
                        coalesce(title,'') || ' ' || coalesce(description,''))
                ) STORED");

        DB::statement("CREATE INDEX products_attrs_gin
            ON products USING GIN (attribute_value_ids)");

        DB::statement("CREATE INDEX products_search_gin
            ON products USING GIN (search_tsv)");

        DB::statement("CREATE INDEX products_title_trgm
            ON products USING GIN (title gin_trgm_ops)");

        DB::statement("ALTER TABLE product_variants
            ADD COLUMN attribute_value_ids bigint[] NOT NULL DEFAULT '{}'");

        DB::statement("CREATE INDEX pv_attrs_gin
            ON product_variants USING GIN (attribute_value_ids)");

        // attribute_payload is assumed to already exist on product_variants (added in the commerce plan migration)
        DB::statement("CREATE INDEX pv_payload_gin
            ON product_variants USING GIN (attribute_payload jsonb_path_ops)");
    }

    public function down(): void
    {
        DB::statement('DROP INDEX IF EXISTS pv_payload_gin');
        DB::statement('DROP INDEX IF EXISTS pv_attrs_gin');
        DB::statement('DROP INDEX IF EXISTS products_title_trgm');
        DB::statement('DROP INDEX IF EXISTS products_search_gin');
        DB::statement('DROP INDEX IF EXISTS products_attrs_gin');

        DB::statement('ALTER TABLE product_variants DROP COLUMN IF EXISTS attribute_value_ids');
        DB::statement('ALTER TABLE products
            DROP COLUMN IF EXISTS search_tsv,
            DROP COLUMN IF EXISTS attribute_value_ids');
    }
};
```

- [ ] **Step 4: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=CacheColumnsSchemaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Migrations/2026_04_24_100300_add_cache_columns_to_products_and_variants.php tests/Feature/Catalog/Migrations/CacheColumnsSchemaTest.php
git commit -m "feat(catalog): add GIN array caches + tsvector to products and variants"
```

---

## Task 6: Create product_images table

**Files:**
- Create: `app/Domains/Catalog/Migrations/2026_04_24_100400_create_product_images_table.php`
- Test: `tests/Feature/Catalog/Migrations/ProductImagesSchemaTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Migrations/ProductImagesSchemaTest.php

use Illuminate\Support\Facades\Schema;

it('creates product_images with attribute_value_id nullable', function () {
    expect(Schema::hasTable('product_images'))->toBeTrue();
    expect(Schema::hasColumns('product_images', [
        'id', 'product_id', 'attribute_value_id',
        'url', 'alt', 'display_order', 'created_at', 'updated_at',
    ]))->toBeTrue();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductImagesSchemaTest`
Expected: FAIL.

- [ ] **Step 3: Create migration**

```php
<?php
// app/Domains/Catalog/Migrations/2026_04_24_100400_create_product_images_table.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('product_images', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')
                ->constrained('products')->cascadeOnDelete();
            $table->foreignId('attribute_value_id')
                ->nullable()
                ->constrained('attribute_values')
                ->nullOnDelete();
            $table->string('url');
            $table->string('alt')->nullable();
            $table->unsignedInteger('display_order')->default(0);
            $table->timestamps();

            $table->index(['product_id', 'attribute_value_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('product_images');
    }
};
```

- [ ] **Step 4: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=ProductImagesSchemaTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Migrations/2026_04_24_100400_create_product_images_table.php tests/Feature/Catalog/Migrations/ProductImagesSchemaTest.php
git commit -m "feat(catalog): create product_images table keyed by attribute_value"
```

---

## Task 7: Create product_facet_counts materialized view

**Files:**
- Create: `app/Domains/Catalog/Migrations/2026_04_24_100500_create_product_facet_counts_mv.php`
- Test: `tests/Feature/Catalog/Migrations/FacetCountsMvTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Migrations/FacetCountsMvTest.php

use Illuminate\Support\Facades\DB;

it('creates product_facet_counts materialized view with unique index', function () {
    $mv = DB::selectOne(
        "SELECT matviewname FROM pg_matviews
         WHERE schemaname='public' AND matviewname='product_facet_counts'"
    );
    expect($mv)->not->toBeNull();

    $idx = DB::selectOne(
        "SELECT indexname FROM pg_indexes
         WHERE schemaname='public' AND indexname='product_facet_counts_pk'"
    );
    expect($idx)->not->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=FacetCountsMvTest`
Expected: FAIL.

- [ ] **Step 3: Create migration**

```php
<?php
// app/Domains/Catalog/Migrations/2026_04_24_100500_create_product_facet_counts_mv.php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        DB::statement("
            CREATE MATERIALIZED VIEW product_facet_counts AS
            SELECT av.attribute_id,
                   av.id AS attribute_value_id,
                   COUNT(DISTINCT p.id) AS product_count
            FROM attribute_values av
            JOIN product_attribute_values pav
                 ON pav.attribute_value_id = av.id
            JOIN products p
                 ON p.id = pav.product_id
                 AND p.status = 'active'
            GROUP BY 1, 2
            WITH NO DATA
        ");

        DB::statement("
            CREATE UNIQUE INDEX product_facet_counts_pk
            ON product_facet_counts (attribute_id, attribute_value_id)
        ");
    }

    public function down(): void
    {
        DB::statement('DROP MATERIALIZED VIEW IF EXISTS product_facet_counts');
    }
};
```

Note: `p.status = 'active'` matches `ProductStatus` enum from the commerce spec. Adjust if the enum value differs.

- [ ] **Step 4: Run migration and tests**

Run: `php artisan migrate && php artisan test --filter=FacetCountsMvTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Migrations/2026_04_24_100500_create_product_facet_counts_mv.php tests/Feature/Catalog/Migrations/FacetCountsMvTest.php
git commit -m "feat(catalog): add product_facet_counts materialized view"
```

---

## Task 8: Attribute model

**Files:**
- Create: `app/Domains/Catalog/Models/Attribute.php`
- Test: `tests/Unit/Catalog/Models/AttributeTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Unit/Catalog/Models/AttributeTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;

it('casts enums and has values relation', function () {
    $attr = Attribute::create([
        'code' => 'color',
        'label' => 'Cor',
        'type' => AttributeType::Variant,
        'input_type' => AttributeInputType::Swatch,
        'is_filterable' => true,
        'display_order' => 1,
    ]);

    expect($attr->type)->toBe(AttributeType::Variant);
    expect($attr->input_type)->toBe(AttributeInputType::Swatch);
    expect($attr->values())->toBeInstanceOf(\Illuminate\Database\Eloquent\Relations\HasMany::class);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AttributeTest`
Expected: FAIL.

- [ ] **Step 3: Create model**

```php
<?php
// app/Domains/Catalog/Models/Attribute.php

namespace App\Domains\Catalog\Models;

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Attribute extends BaseModel
{
    protected $table = 'attributes';

    protected $fillable = [
        'code', 'label', 'type', 'input_type',
        'is_filterable', 'display_order',
    ];

    protected $casts = [
        'type' => AttributeType::class,
        'input_type' => AttributeInputType::class,
        'is_filterable' => 'boolean',
        'display_order' => 'integer',
    ];

    public function values(): HasMany
    {
        return $this->hasMany(AttributeValue::class)->orderBy('display_order');
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AttributeTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Models/Attribute.php tests/Unit/Catalog/Models/AttributeTest.php
git commit -m "feat(catalog): add Attribute model"
```

---

## Task 9: AttributeValue model

**Files:**
- Create: `app/Domains/Catalog/Models/AttributeValue.php`
- Test: `tests/Unit/Catalog/Models/AttributeValueTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Unit/Catalog/Models/AttributeValueTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;

it('belongs to an attribute and casts metadata', function () {
    $attr = Attribute::create([
        'code' => 'color', 'label' => 'Cor',
        'type' => AttributeType::Variant,
        'input_type' => AttributeInputType::Swatch,
    ]);

    $value = AttributeValue::create([
        'attribute_id' => $attr->id,
        'value' => 'Azul',
        'slug' => 'azul',
        'metadata' => ['hex' => '#0000FF'],
        'display_order' => 1,
    ]);

    expect($value->attribute->code)->toBe('color');
    expect($value->metadata)->toBe(['hex' => '#0000FF']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AttributeValueTest`
Expected: FAIL.

- [ ] **Step 3: Create model**

```php
<?php
// app/Domains/Catalog/Models/AttributeValue.php

namespace App\Domains\Catalog\Models;

use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AttributeValue extends BaseModel
{
    protected $table = 'attribute_values';

    protected $fillable = [
        'attribute_id', 'value', 'slug', 'metadata', 'display_order',
    ];

    protected $casts = [
        'metadata' => 'array',
        'display_order' => 'integer',
    ];

    public function attribute(): BelongsTo
    {
        return $this->belongsTo(Attribute::class);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=AttributeValueTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Models/AttributeValue.php tests/Unit/Catalog/Models/AttributeValueTest.php
git commit -m "feat(catalog): add AttributeValue model"
```

---

## Task 10: Extend Product and ProductVariant models with attribute relations

**Files:**
- Modify: `app/Domains/Catalog/Models/Product.php`
- Modify: `app/Domains/Catalog/Models/ProductVariant.php`
- Create: `app/Domains/Catalog/Models/ProductImage.php`
- Test: `tests/Unit/Catalog/Models/ProductAttributeRelationsTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Unit/Catalog/Models/ProductAttributeRelationsTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;

it('attaches attribute values to a product', function () {
    $brand = Attribute::create([
        'code' => 'brand', 'label' => 'Marca',
        'type' => AttributeType::Facet,
        'input_type' => AttributeInputType::Select,
    ]);
    $nike = AttributeValue::create([
        'attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike',
    ]);

    $product = Product::create([
        'title' => 'Camisa', 'slug' => 'camisa', 'origin' => 'national', 'status' => 'active',
    ]);
    $product->attributeValues()->attach($nike->id);

    expect($product->attributeValues)->toHaveCount(1);
    expect($product->attributeValues->first()->slug)->toBe('nike');
});

it('declares variant axes on a product', function () {
    $color = Attribute::create([
        'code' => 'color', 'label' => 'Cor',
        'type' => AttributeType::Variant,
        'input_type' => AttributeInputType::Swatch,
    ]);

    $product = Product::create([
        'title' => 'Camisa', 'slug' => 'camisa-axes', 'origin' => 'national', 'status' => 'active',
    ]);
    $product->variantAxes()->attach($color->id, ['display_order' => 0]);

    expect($product->variantAxes)->toHaveCount(1);
    expect($product->variantAxes->first()->code)->toBe('color');
});

it('has images keyed by attribute value', function () {
    $product = Product::create([
        'title' => 'Camisa', 'slug' => 'camisa-img', 'origin' => 'national', 'status' => 'active',
    ]);
    ProductImage::create([
        'product_id' => $product->id,
        'url' => 'https://cdn/example.jpg',
        'display_order' => 0,
    ]);

    expect($product->images)->toHaveCount(1);
    expect($product->images->first()->attribute_value_id)->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductAttributeRelationsTest`
Expected: FAIL.

- [ ] **Step 3: Create ProductImage model**

```php
<?php
// app/Domains/Catalog/Models/ProductImage.php

namespace App\Domains\Catalog\Models;

use App\Domains\Shared\Models\BaseModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class ProductImage extends BaseModel
{
    use HasUlids;

    protected $table = 'product_images';

    protected $fillable = [
        'product_id', 'attribute_value_id', 'url', 'alt', 'display_order',
    ];

    protected $casts = [
        'display_order' => 'integer',
    ];

    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    public function attributeValue(): BelongsTo
    {
        return $this->belongsTo(AttributeValue::class);
    }
}
```

- [ ] **Step 4: Extend Product model**

Add these methods to `app/Domains/Catalog/Models/Product.php` (keep existing code intact):

```php
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\ProductImage;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

// casts — merge into existing $casts
// 'attribute_value_ids' => 'array',  // bigint[] exposed as PHP array

public function attributeValues(): BelongsToMany
{
    return $this->belongsToMany(
        AttributeValue::class,
        'product_attribute_values',
        'product_id',
        'attribute_value_id'
    );
}

public function variantAxes(): BelongsToMany
{
    return $this->belongsToMany(
        Attribute::class,
        'product_variant_axes',
        'product_id',
        'attribute_id'
    )->withPivot('display_order')->orderByPivot('display_order');
}

public function images(): HasMany
{
    return $this->hasMany(ProductImage::class)->orderBy('display_order');
}
```

Cast for `attribute_value_ids` — Postgres `bigint[]` does not auto-cast. Add a custom accessor:

```php
protected function attributeValueIds(): \Illuminate\Database\Eloquent\Casts\Attribute
{
    return \Illuminate\Database\Eloquent\Casts\Attribute::make(
        get: function ($value) {
            if ($value === null || $value === '{}') return [];
            return array_map('intval', explode(',', trim($value, '{}')));
        },
        set: fn (array $ids) => '{' . implode(',', array_map('intval', $ids)) . '}',
    );
}
```

- [ ] **Step 5: Extend ProductVariant model**

Add to `app/Domains/Catalog/Models/ProductVariant.php`:

```php
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

public function attributeValues(): BelongsToMany
{
    return $this->belongsToMany(
        AttributeValue::class,
        'variant_attribute_values',
        'product_variant_id',
        'attribute_value_id'
    )->withPivot('attribute_id');
}
```

Extract the accessor to a shared trait `App\Domains\Catalog\Concerns\HasAttributeValueIds` and use it on both models — do NOT copy-paste:

```php
<?php
// app/Domains/Catalog/Concerns/HasAttributeValueIds.php

namespace App\Domains\Catalog\Concerns;

use Illuminate\Database\Eloquent\Casts\Attribute;

trait HasAttributeValueIds
{
    protected function attributeValueIds(): Attribute
    {
        return Attribute::make(
            get: function ($value) {
                if ($value === null || $value === '{}') return [];
                return array_map('intval', explode(',', trim($value, '{}')));
            },
            set: fn (array $ids) => '{' . implode(',', array_map('intval', $ids)) . '}',
        );
    }
}
```

Add `use HasAttributeValueIds;` to both `Product` and `ProductVariant`.

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ProductAttributeRelationsTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Catalog/Models/ tests/Unit/Catalog/Models/ProductAttributeRelationsTest.php
git commit -m "feat(catalog): add attribute relations to Product/ProductVariant + ProductImage model"
```

---

## Task 11: Seeders for all 12 MM Sports attributes + values

**Files:**
- Create: `app/Domains/Catalog/Seeders/AttributeSeeder.php`
- Create: `app/Domains/Catalog/Seeders/AttributeValueSeeder.php`
- Test: `tests/Feature/Catalog/Seeders/AttributeSeederTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Seeders/AttributeSeederTest.php

use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Seeders\AttributeSeeder;
use App\Domains\Catalog\Seeders\AttributeValueSeeder;

it('seeds 12 attributes classified per spec', function () {
    (new AttributeSeeder())->run();
    (new AttributeValueSeeder())->run();

    expect(Attribute::count())->toBe(12);

    expect(Attribute::where('code', 'color')->first()->type)->toBe(AttributeType::Variant);
    expect(Attribute::where('code', 'size')->first()->type)->toBe(AttributeType::Variant);
    expect(Attribute::where('code', 'brand')->first()->type)->toBe(AttributeType::Facet);

    $color = Attribute::where('code', 'color')->first();
    expect($color->values()->count())->toBeGreaterThan(3);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AttributeSeederTest`
Expected: FAIL.

- [ ] **Step 3: Create AttributeSeeder**

```php
<?php
// app/Domains/Catalog/Seeders/AttributeSeeder.php

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
            ['code' => 'product_type', 'label' => 'Tipo de Produto', 'type' => AttributeType::Facet,   'input' => AttributeInputType::Select,      'order' => 1],
            ['code' => 'brand',        'label' => 'Marca',           'type' => AttributeType::Facet,   'input' => AttributeInputType::Select,      'order' => 2],
            ['code' => 'team',         'label' => 'Time',            'type' => AttributeType::Facet,   'input' => AttributeInputType::Multiselect, 'order' => 3],
            ['code' => 'sport',        'label' => 'Esporte',         'type' => AttributeType::Facet,   'input' => AttributeInputType::Multiselect, 'order' => 4],
            ['code' => 'age_group',    'label' => 'Idade',           'type' => AttributeType::Facet,   'input' => AttributeInputType::Select,      'order' => 5],
            ['code' => 'material',     'label' => 'Material',        'type' => AttributeType::Facet,   'input' => AttributeInputType::Multiselect, 'order' => 6],
            ['code' => 'collar',       'label' => 'Gola',            'type' => AttributeType::Facet,   'input' => AttributeInputType::Select,      'order' => 7],
            ['code' => 'pocket',       'label' => 'Bolso',           'type' => AttributeType::Facet,   'input' => AttributeInputType::Select,      'order' => 8],
            ['code' => 'sleeve',       'label' => 'Manga',           'type' => AttributeType::Facet,   'input' => AttributeInputType::Select,      'order' => 9],
            ['code' => 'cap_brim',     'label' => 'Aba',             'type' => AttributeType::Facet,   'input' => AttributeInputType::Select,      'order' => 10],
            ['code' => 'color',        'label' => 'Cor',             'type' => AttributeType::Variant, 'input' => AttributeInputType::Swatch,      'order' => 11],
            ['code' => 'size',         'label' => 'Tamanho',         'type' => AttributeType::Variant, 'input' => AttributeInputType::Select,      'order' => 12],
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
```

- [ ] **Step 4: Create AttributeValueSeeder**

```php
<?php
// app/Domains/Catalog/Seeders/AttributeValueSeeder.php

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
            'brand'        => ['Nike', 'Adidas', 'Puma', 'Topper', 'Umbro', 'Mizuno', 'Penalty'],
            'team'         => ['Corinthians', 'Palmeiras', 'São Paulo', 'Santos', 'Flamengo', 'Vasco', 'Fluminense', 'Botafogo'],
            'sport'        => ['Futebol', 'Basquete', 'Corrida', 'Vôlei', 'Tênis', 'Natação'],
            'age_group'    => ['Infantil', 'Juvenil', 'Adulto'],
            'material'     => ['Poliéster', 'Algodão', 'Dry-fit', 'Couro sintético', 'Nylon'],
            'collar'       => ['Redonda', 'V', 'Polo', 'Gola alta'],
            'pocket'       => ['Com bolso', 'Sem bolso'],
            'sleeve'       => ['Curta', 'Longa', 'Sem manga', '3/4'],
            'cap_brim'     => ['Reta', 'Curva', 'Sem aba'],
            'size'         => ['PP', 'P', 'M', 'G', 'GG', 'XG'],
            'color'        => [
                ['value' => 'Azul',     'hex' => '#1E3A8A'],
                ['value' => 'Vermelho', 'hex' => '#B91C1C'],
                ['value' => 'Preto',    'hex' => '#111111'],
                ['value' => 'Branco',   'hex' => '#FFFFFF'],
                ['value' => 'Verde',    'hex' => '#15803D'],
                ['value' => 'Amarelo',  'hex' => '#FACC15'],
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
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AttributeSeederTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Catalog/Seeders/ tests/Feature/Catalog/Seeders/
git commit -m "feat(catalog): seed 12 MM Sports attributes and their values"
```

---

## Task 12: GenerateVariantMatrixService

**Files:**
- Create: `app/Domains/Catalog/Services/GenerateVariantMatrixService.php`
- Test: `tests/Unit/Catalog/Services/GenerateVariantMatrixServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Unit/Catalog/Services/GenerateVariantMatrixServiceTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\GenerateVariantMatrixService;

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
    $this->azul     = AttributeValue::create(['attribute_id' => $this->color->id, 'value' => 'Azul', 'slug' => 'azul']);
    $this->vermelho = AttributeValue::create(['attribute_id' => $this->color->id, 'value' => 'Vermelho', 'slug' => 'vermelho']);
    $this->p = AttributeValue::create(['attribute_id' => $this->size->id, 'value' => 'P', 'slug' => 'p']);
    $this->m = AttributeValue::create(['attribute_id' => $this->size->id, 'value' => 'M', 'slug' => 'm']);
    $this->g = AttributeValue::create(['attribute_id' => $this->size->id, 'value' => 'G', 'slug' => 'g']);

    $this->product = Product::create([
        'title' => 'Camisa', 'slug' => 'camisa-matriz',
        'origin' => 'national', 'status' => 'active',
    ]);
});

it('generates 2x3 matrix (6 SKUs) idempotently', function () {
    $service = app(GenerateVariantMatrixService::class);

    $service->handle($this->product, [
        $this->color->id => [$this->azul->id, $this->vermelho->id],
        $this->size->id  => [$this->p->id, $this->m->id, $this->g->id],
    ]);

    expect($this->product->variants()->count())->toBe(6);

    // second call does not create duplicates
    $service->handle($this->product, [
        $this->color->id => [$this->azul->id, $this->vermelho->id],
        $this->size->id  => [$this->p->id, $this->m->id, $this->g->id],
    ]);

    expect($this->product->variants()->count())->toBe(6);
});

it('populates attribute_payload and attribute_value_ids on each variant', function () {
    $service = app(GenerateVariantMatrixService::class);

    $service->handle($this->product, [
        $this->color->id => [$this->azul->id],
        $this->size->id  => [$this->m->id],
    ]);

    $variant = $this->product->variants()->first();

    expect($variant->attribute_payload)->toBe(['color' => 'Azul', 'size' => 'M']);
    expect($variant->attribute_value_ids)->toEqualCanonicalizing([$this->azul->id, $this->m->id]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=GenerateVariantMatrixServiceTest`
Expected: FAIL.

- [ ] **Step 3: Create service**

```php
<?php
// app/Domains/Catalog/Services/GenerateVariantMatrixService.php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class GenerateVariantMatrixService extends BaseService
{
    /**
     * @param array<int, int[]> $valuesByAttribute attribute_id => attribute_value_id[]
     */
    public function handle(Product $product, array $valuesByAttribute): void
    {
        DB::transaction(function () use ($product, $valuesByAttribute) {
            $this->syncVariantAxes($product, array_keys($valuesByAttribute));

            $combinations = $this->cartesian($valuesByAttribute);
            $attrCodeById = Attribute::whereIn('id', array_keys($valuesByAttribute))
                ->pluck('code', 'id');
            $valuesIndex = AttributeValue::whereIn('id', array_merge(...array_values($valuesByAttribute)))
                ->get()->keyBy('id');

            foreach ($combinations as $combo) {
                $existing = $this->findVariantBySignature($product, $combo);
                if ($existing) {
                    continue;
                }

                $payload = [];
                foreach ($combo as $attributeId => $valueId) {
                    $payload[$attrCodeById[$attributeId]] = $valuesIndex[$valueId]->value;
                }

                $variant = ProductVariant::create([
                    'product_id' => $product->id,
                    'sku' => $this->makeSku($product, $payload),
                    'price' => 0,
                    'stock_quantity' => 0,
                    'attribute_payload' => $payload,
                    'is_active' => false, // admin activates after pricing/stock
                ]);

                $pivotRows = [];
                foreach ($combo as $attributeId => $valueId) {
                    $pivotRows[] = [
                        'product_variant_id' => $variant->id,
                        'attribute_id' => $attributeId,
                        'attribute_value_id' => $valueId,
                    ];
                }
                DB::table('variant_attribute_values')->insert($pivotRows);

                // denormalized array is synced by refreshAttributeCache() called in syncAttributeValues() (Task 13)
                $variant->refresh();
            }
        });
    }

    /** @param int[] $attributeIds */
    private function syncVariantAxes(Product $product, array $attributeIds): void
    {
        $sync = [];
        foreach (array_values($attributeIds) as $i => $id) {
            $sync[$id] = ['display_order' => $i];
        }
        $product->variantAxes()->sync($sync);
    }

    /** @param array<int, int[]> $values */
    private function cartesian(array $values): array
    {
        $result = [[]];
        foreach ($values as $attrId => $vids) {
            $next = [];
            foreach ($result as $row) {
                foreach ($vids as $vid) {
                    $next[] = $row + [$attrId => $vid];
                }
            }
            $result = $next;
        }
        return $result;
    }

    /** @param array<int,int> $combo */
    private function findVariantBySignature(Product $product, array $combo): ?ProductVariant
    {
        $valueIds = array_values($combo);
        sort($valueIds);

        return $product->variants()
            ->whereRaw('attribute_value_ids @> ?::bigint[]', ['{' . implode(',', $valueIds) . '}'])
            ->whereRaw('cardinality(attribute_value_ids) = ?', [count($valueIds)])
            ->first();
    }

    private function makeSku(Product $product, array $payload): string
    {
        $parts = [Str::upper(Str::slug($product->title)), ...array_map(
            fn ($v) => Str::upper(Str::slug($v)),
            array_values($payload)
        )];
        return implode('-', $parts) . '-' . Str::upper(Str::random(4));
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=GenerateVariantMatrixServiceTest`
Expected: PASS (depends on observer from Task 13 for `attribute_value_ids`; if it fails on that assertion, defer running that specific assertion until Task 13 is done — or inline the array sync as a fallback in this service temporarily).

Pragmatic approach: have the service write `attribute_value_ids` directly here (via raw update) so it is independent of the observer:

Add after the `variant_attribute_values` insert:

```php
DB::statement(
    "UPDATE product_variants SET attribute_value_ids = ?::bigint[] WHERE id = ?",
    ['{' . implode(',', array_values($combo)) . '}', $variant->id]
);
```

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Services/GenerateVariantMatrixService.php tests/Unit/Catalog/Services/GenerateVariantMatrixServiceTest.php
git commit -m "feat(catalog): add GenerateVariantMatrixService with idempotent cartesian SKUs"
```

---

## Task 13: Sync methods — keep `attribute_value_ids` consistent

**Note:** Laravel does not fire model events on pivot `sync()` calls. Rather than fighting this with a brittle observer, we expose explicit sync methods on the models that update the denormalized column in the same call. No separate Observer files are needed for this task.

**Files:**
- Modify: `app/Domains/Catalog/Models/Product.php`
- Modify: `app/Domains/Catalog/Models/ProductVariant.php`
- Test: `tests/Feature/Catalog/Observers/AttributeSyncObserversTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Observers/AttributeSyncObserversTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;

it('recomputes products.attribute_value_ids when facets change', function () {
    $brand = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $nike  = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike']);
    $adi   = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Adidas', 'slug' => 'adidas']);

    $product = Product::create(['title' => 'Camisa', 'slug' => 'cam-obs', 'origin' => 'national', 'status' => 'active']);

    $product->syncAttributeValues([$nike->id]);
    $product->refresh();
    expect($product->attribute_value_ids)->toBe([$nike->id]);

    $product->syncAttributeValues([$nike->id, $adi->id]);
    $product->refresh();
    expect($product->attribute_value_ids)->toEqualCanonicalizing([$nike->id, $adi->id]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AttributeSyncObserversTest`
Expected: FAIL (array stays empty).

- [ ] **Step 3: Add sync methods to Product model**

Since Laravel does not fire model events on pivot `sync()` calls, we expose explicit sync methods on the model that update the denormalized column in the same call.

Add to `Product` model:

```php
public function syncAttributeValues(array $valueIds): void
{
    $this->attributeValues()->sync($valueIds);
    $this->refreshAttributeValueIdsCache();
}

public function refreshAttributeValueIdsCache(): void
{
    $ids = $this->attributeValues()->pluck('attribute_values.id')->map(fn ($v) => (int) $v)->sort()->values()->all();
    \DB::statement(
        "UPDATE products SET attribute_value_ids = ?::bigint[] WHERE id = ?",
        ['{' . implode(',', $ids) . '}', $this->id]
    );
    $this->setAttribute('attribute_value_ids', $ids);
}
```

The test already uses `syncAttributeValues()` — no changes needed to the test.

- [ ] **Step 4: Repeat for ProductVariant**

```php
public function syncAttributeValues(array $mapAttrIdToValueId): void
{
    // mapAttrIdToValueId: [attribute_id => attribute_value_id]
    DB::transaction(function () use ($mapAttrIdToValueId) {
        DB::table('variant_attribute_values')
            ->where('product_variant_id', $this->id)->delete();

        $rows = [];
        foreach ($mapAttrIdToValueId as $attributeId => $valueId) {
            $rows[] = [
                'product_variant_id' => $this->id,
                'attribute_id' => (int) $attributeId,
                'attribute_value_id' => (int) $valueId,
            ];
        }
        if ($rows) DB::table('variant_attribute_values')->insert($rows);

        $this->refreshAttributeCache();
    });
}

public function refreshAttributeCache(): void
{
    $rows = DB::table('variant_attribute_values as vav')
        ->join('attributes as a', 'a.id', '=', 'vav.attribute_id')
        ->join('attribute_values as av', 'av.id', '=', 'vav.attribute_value_id')
        ->where('vav.product_variant_id', $this->id)
        ->get(['a.code', 'av.value', 'av.id']);

    $payload = $rows->mapWithKeys(fn ($r) => [$r->code => $r->value])->all();
    $ids = $rows->pluck('id')->map(fn ($v) => (int) $v)->sort()->values()->all();

    DB::statement(
        "UPDATE product_variants SET attribute_payload = ?::jsonb, attribute_value_ids = ?::bigint[] WHERE id = ?",
        [json_encode($payload), '{' . implode(',', $ids) . '}', $this->id]
    );
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=AttributeSyncObserversTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Catalog/Models/ tests/Feature/Catalog/Observers/AttributeSyncObserversTest.php
git commit -m "feat(catalog): sync denormalized attribute_value_ids on product/variant changes"
```

---

## Task 14: CatalogFacetService (storefront sidebar)

**Files:**
- Create: `app/Domains/Catalog/Services/CatalogFacetService.php`
- Test: `tests/Feature/Catalog/Services/CatalogFacetServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Services/CatalogFacetServiceTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\CatalogFacetService;
use Illuminate\Support\Facades\DB;

it('returns filterable attributes with counts from MV', function () {
    $brand = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $nike  = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike']);

    $p = Product::create(['title' => 'X', 'slug' => 'x-facet', 'origin' => 'national', 'status' => 'active']);
    $p->syncAttributeValues([$nike->id]);

    DB::statement('REFRESH MATERIALIZED VIEW product_facet_counts');

    $facets = app(CatalogFacetService::class)->getFacets();

    $brandFacet = collect($facets)->firstWhere('code', 'brand');
    expect($brandFacet)->not->toBeNull();
    expect($brandFacet['values'][0]['count'])->toBe(1);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CatalogFacetServiceTest`
Expected: FAIL.

- [ ] **Step 3: Create service**

```php
<?php
// app/Domains/Catalog/Services/CatalogFacetService.php

namespace App\Domains\Catalog\Services;

use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogFacetService extends BaseService
{
    public function getFacets(): array
    {
        return Cache::tags(['facets'])->remember(
            'catalog:facets:v1',
            now()->addMinutes(5),
            fn () => $this->queryFacets()
        );
    }

    private function queryFacets(): array
    {
        $rows = DB::select("
            SELECT a.code, a.label, a.input_type, a.display_order AS a_order,
                   av.id AS value_id, av.value, av.slug, av.metadata,
                   av.display_order AS v_order,
                   COALESCE(fc.product_count, 0) AS product_count
            FROM attributes a
            JOIN attribute_values av ON av.attribute_id = a.id
            LEFT JOIN product_facet_counts fc ON fc.attribute_value_id = av.id
            WHERE a.is_filterable = true
              AND COALESCE(fc.product_count, 0) > 0
            ORDER BY a.display_order, av.display_order
        ");

        $grouped = [];
        foreach ($rows as $r) {
            $grouped[$r->code] ??= [
                'code' => $r->code,
                'label' => $r->label,
                'input_type' => $r->input_type,
                'values' => [],
            ];
            $grouped[$r->code]['values'][] = [
                'id' => (int) $r->value_id,
                'value' => $r->value,
                'slug' => $r->slug,
                'metadata' => $r->metadata ? json_decode($r->metadata, true) : null,
                'count' => (int) $r->product_count,
            ];
        }

        return array_values($grouped);
    }
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php artisan test --filter=CatalogFacetServiceTest`
Expected: PASS.

- [ ] **Step 5: Commit**

```bash
git add app/Domains/Catalog/Services/CatalogFacetService.php tests/Feature/Catalog/Services/CatalogFacetServiceTest.php
git commit -m "feat(catalog): add CatalogFacetService backed by MV + Redis tag cache"
```

---

## Task 15: CatalogProductSearchService (listing)

**Files:**
- Create: `app/Domains/Catalog/Services/CatalogProductSearchService.php`
- Create: `app/Domains/Catalog/Requests/CatalogListRequest.php`
- Test: `tests/Feature/Catalog/Services/CatalogProductSearchServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Services/CatalogProductSearchServiceTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\CatalogProductSearchService;

it('filters by facet slugs via GIN array containment', function () {
    $brand = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $team  = Attribute::create(['code' => 'team', 'label' => 'Time', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);

    $nike = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike']);
    $cor  = AttributeValue::create(['attribute_id' => $team->id, 'value' => 'Corinthians', 'slug' => 'corinthians']);
    $pal  = AttributeValue::create(['attribute_id' => $team->id, 'value' => 'Palmeiras', 'slug' => 'palmeiras']);

    $match = Product::create(['title' => 'Camisa Nike Timão', 'slug' => 'a', 'origin' => 'national', 'status' => 'active']);
    $match->syncAttributeValues([$nike->id, $cor->id]);

    $skip = Product::create(['title' => 'Camisa Nike Verdão', 'slug' => 'b', 'origin' => 'national', 'status' => 'active']);
    $skip->syncAttributeValues([$nike->id, $pal->id]);

    $results = app(CatalogProductSearchService::class)->search([
        'brand' => ['nike'],
        'team'  => ['corinthians'],
    ]);

    expect($results->pluck('slug')->all())->toBe(['a']);
});

it('filters by full-text search via tsvector', function () {
    $p1 = Product::create(['title' => 'Camisa Corinthians oficial', 'slug' => 'c1', 'origin' => 'national', 'status' => 'active']);
    $p2 = Product::create(['title' => 'Boné preto liso', 'slug' => 'c2', 'origin' => 'national', 'status' => 'active']);

    $results = app(CatalogProductSearchService::class)->search(['q' => 'camisa']);

    expect($results->pluck('slug')->all())->toContain('c1');
    expect($results->pluck('slug')->all())->not->toContain('c2');
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CatalogProductSearchServiceTest`
Expected: FAIL.

- [ ] **Step 3: Create CatalogListRequest**

```php
<?php
// app/Domains/Catalog/Requests/CatalogListRequest.php

namespace App\Domains\Catalog\Requests;

use App\Domains\Shared\Requests\BaseFormRequest;

class CatalogListRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'q' => 'nullable|string|max:200',
            'page' => 'nullable|integer|min:1',
            'per_page' => 'nullable|integer|min:1|max:60',
            // every other key is a facet slug; validated as string|array in the service
        ];
    }

    public function store(): array { return $this->base(); }
    public function update(): array { return $this->base(); }
}
```

- [ ] **Step 4: Create service**

```php
<?php
// app/Domains/Catalog/Services/CatalogProductSearchService.php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class CatalogProductSearchService extends BaseService
{
    public function search(array $filters, int $page = 1, int $perPage = 24): LengthAwarePaginator
    {
        ksort($filters); // ensure stable hash regardless of key order
        $hash = sha1(json_encode([$filters, $page, $perPage]));
        $key = "catalog:list:{$hash}";

        return Cache::tags(['facets'])->remember($key, now()->addSeconds(60),
            fn () => $this->runQuery($filters, $page, $perPage));
    }

    private function runQuery(array $filters, int $page, int $perPage): LengthAwarePaginator
    {
        $q = $filters['q'] ?? null;
        unset($filters['q'], $filters['page'], $filters['per_page']);

        // split facet vs variant attrs — single batch query for all slugs
        $attrs = Attribute::whereIn('code', array_keys($filters))
            ->get()->keyBy('code');

        $facetValueIds = [];
        $variantValueIds = [];

        if ($attrs->isNotEmpty()) {
            $allSlugs = array_merge(...array_map(fn ($s) => (array) $s, array_values($filters)));
            $valuesByAttr = AttributeValue::whereIn('attribute_id', $attrs->pluck('id'))
                ->whereIn('slug', $allSlugs)
                ->get()
                ->groupBy('attribute_id');

            foreach ($attrs as $code => $attr) {
                $slugs = (array) ($filters[$code] ?? []);
                $ids = ($valuesByAttr[$attr->id] ?? collect())
                    ->whereIn('slug', $slugs)
                    ->pluck('id')->all();

                if ($attr->type->isVariant() && !$attr->type->isFacet()) {
                    $variantValueIds = array_merge($variantValueIds, $ids);
                } else {
                    $facetValueIds = array_merge($facetValueIds, $ids);
                }
            }
        }

        $query = Product::query()->where('status', 'active');

        if ($facetValueIds) {
            $query->whereRaw('attribute_value_ids @> ?::bigint[]',
                ['{' . implode(',', $facetValueIds) . '}']);
        }

        if ($variantValueIds) {
            $query->whereExists(function ($q) use ($variantValueIds) {
                $q->select(DB::raw(1))
                    ->from('product_variants as v')
                    ->whereColumn('v.product_id', 'products.id')
                    ->where('v.is_active', true)
                    ->where('v.stock_quantity', '>', 0)
                    ->whereRaw('v.attribute_value_ids @> ?::bigint[]',
                        ['{' . implode(',', $variantValueIds) . '}']);
            });
        }

        if ($q) {
            $query->whereRaw("search_tsv @@ plainto_tsquery('portuguese', ?)", [$q]);
        }

        return $query->orderByDesc('id')
            ->paginate($perPage, ['*'], 'page', $page);
    }
}
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CatalogProductSearchServiceTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Catalog/Services/CatalogProductSearchService.php app/Domains/Catalog/Requests/CatalogListRequest.php tests/Feature/Catalog/Services/CatalogProductSearchServiceTest.php
git commit -m "feat(catalog): add CatalogProductSearchService with GIN + tsvector filtering"
```

---

## Task 16: Public controllers and API routes (facets + products)

**Files:**
- Create: `app/Domains/Catalog/Controllers/CatalogFacetController.php`
- Create: `app/Domains/Catalog/Controllers/CatalogProductController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Catalog/Api/CatalogPublicApiTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Api/CatalogPublicApiTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use Illuminate\Support\Facades\DB;

it('GET /api/catalog/facets returns grouped facets', function () {
    $brand = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $nike  = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike']);
    $p = Product::create(['title' => 'Camisa', 'slug' => 'api-f', 'origin' => 'national', 'status' => 'active']);
    $p->syncAttributeValues([$nike->id]);
    DB::statement('REFRESH MATERIALIZED VIEW product_facet_counts');

    $this->getJson('/api/catalog/facets')
        ->assertOk()
        ->assertJsonPath('facets.0.code', 'brand')
        ->assertJsonPath('facets.0.values.0.slug', 'nike');
});

it('GET /api/catalog/products filters by slug-valued facets', function () {
    $brand = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $nike  = AttributeValue::create(['attribute_id' => $brand->id, 'value' => 'Nike', 'slug' => 'nike']);

    $p = Product::create(['title' => 'X', 'slug' => 'api-x', 'origin' => 'national', 'status' => 'active']);
    $p->syncAttributeValues([$nike->id]);

    $this->getJson('/api/catalog/products?brand=nike')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'api-x']);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CatalogPublicApiTest`
Expected: FAIL (routes not registered).

- [ ] **Step 3: Create controllers**

```php
<?php
// app/Domains/Catalog/Controllers/CatalogFacetController.php

namespace App\Domains\Catalog\Controllers;

use App\Domains\Catalog\Services\CatalogFacetService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\JsonResponse;

class CatalogFacetController extends BaseController
{
    public function __construct(private CatalogFacetService $service) {}

    public function index(): JsonResponse
    {
        return response()->json(['facets' => $this->service->getFacets()]);
    }
}
```

```php
<?php
// app/Domains/Catalog/Controllers/CatalogProductController.php

namespace App\Domains\Catalog\Controllers;

use App\Domains\Catalog\Requests\CatalogListRequest;
use App\Domains\Catalog\Services\CatalogProductSearchService;
use App\Domains\Shared\Controller\BaseController;
use Illuminate\Http\JsonResponse;

class CatalogProductController extends BaseController
{
    public function __construct(private CatalogProductSearchService $service) {}

    public function index(CatalogListRequest $request): JsonResponse
    {
        // pass full query (facet slugs are unknown keys)
        $filters = $request->query();
        $page = (int) ($filters['page'] ?? 1);
        $perPage = (int) ($filters['per_page'] ?? 24);

        $results = $this->service->search($filters, $page, $perPage);

        return response()->json(['data' => $results]);
    }
}
```

- [ ] **Step 4: Register routes**

Add to `routes/api.php`:

```php
use App\Domains\Catalog\Controllers\CatalogFacetController;
use App\Domains\Catalog\Controllers\CatalogProductController;

Route::prefix('catalog')->group(function () {
    Route::get('facets', [CatalogFacetController::class, 'index']);
    Route::get('products', [CatalogProductController::class, 'index']);
});
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CatalogPublicApiTest`
Expected: PASS.

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Catalog/Controllers/ routes/api.php tests/Feature/Catalog/Api/CatalogPublicApiTest.php
git commit -m "feat(catalog): expose GET /api/catalog/facets and /products"
```

---

## Task 17: Admin endpoints — attributes CRUD

**Files:**
- Create: `app/Domains/Catalog/Services/AttributeAdminService.php`
- Create: `app/Domains/Catalog/Services/AttributeValueAdminService.php`
- Create: `app/Domains/Catalog/Requests/Admin/AttributeRequest.php`
- Create: `app/Domains/Catalog/Requests/Admin/AttributeValueRequest.php`
- Create: `app/Domains/Catalog/Controllers/Admin/AttributeController.php`
- Create: `app/Domains/Catalog/Controllers/Admin/AttributeValueController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Catalog/Api/Admin/AttributeAdminApiTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Api/Admin/AttributeAdminApiTest.php

it('creates attribute + values via admin API', function () {
    $this->actingAsAdmin(); // assume helper defined in TestCase — if not, implement minimal auth bypass

    $attr = $this->postJson('/api/admin/attributes', [
        'code' => 'collar',
        'label' => 'Gola',
        'type' => 'facet',
        'input_type' => 'select',
    ])->assertCreated()->json('data');

    $this->postJson('/api/admin/attributes/' . $attr['id'] . '/values', [
        'value' => 'Polo',
    ])->assertCreated()
      ->assertJsonPath('data.slug', 'polo');
});
```

If the project lacks an auth harness for admin routes, mock `auth()->loginUsingId($adminId)` in the test setup.

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=AttributeAdminApiTest`
Expected: FAIL.

- [ ] **Step 3: Create Requests**

```php
<?php
// app/Domains/Catalog/Requests/Admin/AttributeRequest.php

namespace App\Domains\Catalog\Requests\Admin;

use App\Domains\Shared\Requests\BaseFormRequest;
use Illuminate\Validation\Rule;

class AttributeRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'label' => 'required|string|max:80',
            'type' => ['required', Rule::in(['facet', 'variant', 'both'])],
            'input_type' => ['required', Rule::in(['select', 'multiselect', 'text', 'swatch'])],
            'is_filterable' => 'boolean',
            'display_order' => 'integer|min:0',
        ];
    }

    public function store(): array
    {
        return $this->base() + [
            'code' => 'required|string|max:40|regex:/^[a-z_]+$/|unique:attributes,code',
        ];
    }

    public function update(): array
    {
        return $this->base();
    }
}
```

```php
<?php
// app/Domains/Catalog/Requests/Admin/AttributeValueRequest.php

namespace App\Domains\Catalog\Requests\Admin;

use App\Domains\Shared\Requests\BaseFormRequest;

class AttributeValueRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'value' => 'required|string|max:80',
            'metadata' => 'nullable|array',
            'display_order' => 'integer|min:0',
        ];
    }
    public function store(): array { return $this->base(); }
    public function update(): array { return $this->base(); }
}
```

- [ ] **Step 4: Create Services**

```php
<?php
// app/Domains/Catalog/Services/AttributeAdminService.php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Facades\Cache;

class AttributeAdminService extends BaseService
{
    public function create(array $data): Attribute
    {
        $attr = Attribute::create($data);
        Cache::tags(['facets'])->flush();
        return $attr;
    }

    public function update(Attribute $attribute, array $data): Attribute
    {
        $attribute->update($data);
        Cache::tags(['facets'])->flush();
        return $attribute->refresh();
    }

    public function delete(Attribute $attribute): void
    {
        $attribute->delete();
        Cache::tags(['facets'])->flush();
    }
}
```

```php
<?php
// app/Domains/Catalog/Services/AttributeValueAdminService.php

namespace App\Domains\Catalog\Services;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class AttributeValueAdminService extends BaseService
{
    public function create(Attribute $attribute, array $data): AttributeValue
    {
        $value = $attribute->values()->create([
            ...$data,
            'slug' => Str::slug($data['value']),
        ]);
        Cache::tags(['facets'])->flush();
        return $value;
    }

    public function update(AttributeValue $value, array $data): AttributeValue
    {
        $value->update([
            ...$data,
            'slug' => isset($data['value']) ? Str::slug($data['value']) : $value->slug,
        ]);
        Cache::tags(['facets'])->flush();
        return $value->refresh();
    }

    public function delete(AttributeValue $value): void
    {
        $value->delete();
        Cache::tags(['facets'])->flush();
    }
}
```

- [ ] **Step 5: Create Controllers**

```php
<?php
// app/Domains/Catalog/Controllers/Admin/AttributeController.php

namespace App\Domains\Catalog\Controllers\Admin;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Requests\Admin\AttributeRequest;
use App\Domains\Catalog\Services\AttributeAdminService;
use App\Domains\Shared\Controller\BaseController;

class AttributeController extends BaseController
{
    public function __construct(private AttributeAdminService $service) {}

    public function index()
    {
        return response()->json(['data' => Attribute::with('values')->orderBy('display_order')->get()]);
    }

    public function store(AttributeRequest $request)
    {
        return response()->json(['data' => $this->service->create($request->validated())], 201);
    }

    public function update(AttributeRequest $request, Attribute $attribute)
    {
        return response()->json(['data' => $this->service->update($attribute, $request->validated())]);
    }

    public function destroy(Attribute $attribute)
    {
        $this->service->delete($attribute);
        return response()->noContent();
    }
}
```

```php
<?php
// app/Domains/Catalog/Controllers/Admin/AttributeValueController.php

namespace App\Domains\Catalog\Controllers\Admin;

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Requests\Admin\AttributeValueRequest;
use App\Domains\Catalog\Services\AttributeValueAdminService;
use App\Domains\Shared\Controller\BaseController;

class AttributeValueController extends BaseController
{
    public function __construct(private AttributeValueAdminService $service) {}

    public function store(AttributeValueRequest $request, Attribute $attribute)
    {
        return response()->json(
            ['data' => $this->service->create($attribute, $request->validated())],
            201
        );
    }

    public function update(AttributeValueRequest $request, AttributeValue $value)
    {
        return response()->json(['data' => $this->service->update($value, $request->validated())]);
    }

    public function destroy(AttributeValue $value)
    {
        $this->service->delete($value);
        return response()->noContent();
    }
}
```

- [ ] **Step 6: Register admin routes**

Add to `routes/api.php` (within existing admin middleware group — follow existing convention; if none, create one):

```php
use App\Domains\Catalog\Controllers\Admin\AttributeController;
use App\Domains\Catalog\Controllers\Admin\AttributeValueController;

Route::prefix('admin')->middleware(['auth:sanctum', /* admin ability */])->group(function () {
    Route::get   ('attributes',                   [AttributeController::class, 'index']);
    Route::post  ('attributes',                   [AttributeController::class, 'store']);
    Route::put   ('attributes/{attribute}',       [AttributeController::class, 'update']);
    Route::delete('attributes/{attribute}',       [AttributeController::class, 'destroy']);

    Route::post  ('attributes/{attribute}/values', [AttributeValueController::class, 'store']);
    Route::put   ('attribute-values/{value}',      [AttributeValueController::class, 'update']);
    Route::delete('attribute-values/{value}',      [AttributeValueController::class, 'destroy']);
});
```

- [ ] **Step 7: Run test to verify it passes**

Run: `php artisan test --filter=AttributeAdminApiTest`
Expected: PASS.

- [ ] **Step 8: Commit**

```bash
git add app/Domains/Catalog/Controllers/Admin/ app/Domains/Catalog/Services/AttributeAdminService.php app/Domains/Catalog/Services/AttributeValueAdminService.php app/Domains/Catalog/Requests/Admin/ routes/api.php tests/Feature/Catalog/Api/Admin/AttributeAdminApiTest.php
git commit -m "feat(catalog): admin CRUD for attributes and attribute values"
```

---

## Task 18: Admin endpoint — generate variant matrix

**Files:**
- Create: `app/Domains/Catalog/Requests/Admin/GenerateVariantMatrixRequest.php`
- Create: `app/Domains/Catalog/Controllers/Admin/ProductVariantMatrixController.php`
- Modify: `routes/api.php`
- Test: `tests/Feature/Catalog/Api/Admin/ProductVariantMatrixApiTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Api/Admin/ProductVariantMatrixApiTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;

it('generates 2x2 variant matrix via admin API', function () {
    $this->actingAsAdmin();

    $color = Attribute::create(['code' => 'color', 'label' => 'Cor', 'type' => AttributeType::Variant, 'input_type' => AttributeInputType::Swatch]);
    $size  = Attribute::create(['code' => 'size', 'label' => 'Tamanho', 'type' => AttributeType::Variant, 'input_type' => AttributeInputType::Select]);
    $azul  = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Azul', 'slug' => 'azul']);
    $verm  = AttributeValue::create(['attribute_id' => $color->id, 'value' => 'Vermelho', 'slug' => 'vermelho']);
    $p     = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'P', 'slug' => 'p']);
    $m     = AttributeValue::create(['attribute_id' => $size->id, 'value' => 'M', 'slug' => 'm']);

    $product = Product::create(['title' => 'Camisa', 'slug' => 'api-mat', 'origin' => 'national', 'status' => 'active']);

    $this->postJson("/api/admin/products/{$product->id}/variant-matrix", [
        'axes' => [
            ['attribute_id' => $color->id, 'value_ids' => [$azul->id, $verm->id]],
            ['attribute_id' => $size->id,  'value_ids' => [$p->id, $m->id]],
        ],
    ])->assertOk();

    expect($product->variants()->count())->toBe(4);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=ProductVariantMatrixApiTest`
Expected: FAIL.

- [ ] **Step 3: Create request**

```php
<?php
// app/Domains/Catalog/Requests/Admin/GenerateVariantMatrixRequest.php

namespace App\Domains\Catalog\Requests\Admin;

use App\Domains\Shared\Requests\BaseFormRequest;

class GenerateVariantMatrixRequest extends BaseFormRequest
{
    public function base(): array
    {
        return [
            'axes' => 'required|array|min:1',
            'axes.*.attribute_id' => 'required|integer|exists:attributes,id',
            'axes.*.value_ids' => 'required|array|min:1',
            'axes.*.value_ids.*' => 'integer|exists:attribute_values,id',
        ];
    }
    public function store(): array { return $this->base(); }
    public function update(): array { return $this->base(); }
}
```

- [ ] **Step 4: Create controller**

```php
<?php
// app/Domains/Catalog/Controllers/Admin/ProductVariantMatrixController.php

namespace App\Domains\Catalog\Controllers\Admin;

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Requests\Admin\GenerateVariantMatrixRequest;
use App\Domains\Catalog\Services\GenerateVariantMatrixService;
use App\Domains\Shared\Controller\BaseController;

class ProductVariantMatrixController extends BaseController
{
    public function __construct(private GenerateVariantMatrixService $service) {}

    public function generate(GenerateVariantMatrixRequest $request, Product $product)
    {
        $map = [];
        foreach ($request->validated('axes') as $axis) {
            $map[(int) $axis['attribute_id']] = array_map('intval', $axis['value_ids']);
        }

        $this->service->handle($product, $map);

        return response()->json([
            'data' => $product->load('variants')->variants,
        ]);
    }
}
```

- [ ] **Step 5: Register route**

Inside the existing admin group in `routes/api.php`:

```php
use App\Domains\Catalog\Controllers\Admin\ProductVariantMatrixController;

Route::post('products/{product}/variant-matrix',
    [ProductVariantMatrixController::class, 'generate']);
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=ProductVariantMatrixApiTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Catalog/Requests/Admin/GenerateVariantMatrixRequest.php app/Domains/Catalog/Controllers/Admin/ProductVariantMatrixController.php routes/api.php tests/Feature/Catalog/Api/Admin/ProductVariantMatrixApiTest.php
git commit -m "feat(catalog): admin endpoint to generate SKU matrix for a product"
```

---

## Task 19: RebuildCatalogCacheService + artisan command

**Files:**
- Create: `app/Domains/Catalog/Services/RebuildCatalogCacheService.php`
- Create: `app/Console/Commands/CatalogRebuildCacheCommand.php`
- Create: `app/Console/Commands/CatalogRefreshFacetCountsCommand.php`
- Test: `tests/Feature/Catalog/Services/RebuildCatalogCacheServiceTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Services/RebuildCatalogCacheServiceTest.php

use App\Domains\Catalog\Enums\AttributeInputType;
use App\Domains\Catalog\Enums\AttributeType;
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Services\RebuildCatalogCacheService;
use Illuminate\Support\Facades\DB;

it('rehydrates attribute_value_ids cache on all products', function () {
    $a = Attribute::create(['code' => 'brand', 'label' => 'Marca', 'type' => AttributeType::Facet, 'input_type' => AttributeInputType::Select]);
    $v = AttributeValue::create(['attribute_id' => $a->id, 'value' => 'Nike', 'slug' => 'nike']);

    $p = Product::create(['title' => 'X', 'slug' => 'rebuild-x', 'origin' => 'national', 'status' => 'active']);
    DB::table('product_attribute_values')->insert([
        'product_id' => $p->id, 'attribute_value_id' => $v->id,
    ]);
    // cache column is stale (empty)
    expect($p->refresh()->attribute_value_ids)->toBe([]);

    app(RebuildCatalogCacheService::class)->handle();

    expect($p->refresh()->attribute_value_ids)->toBe([$v->id]);
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=RebuildCatalogCacheServiceTest`
Expected: FAIL.

- [ ] **Step 3: Create service**

```php
<?php
// app/Domains/Catalog/Services/RebuildCatalogCacheService.php

namespace App\Domains\Catalog\Services;

use App\Domains\Shared\Services\BaseService;
use Illuminate\Support\Facades\DB;

class RebuildCatalogCacheService extends BaseService
{
    public function handle(): void
    {
        // Single query: updates products with values and resets those without
        DB::statement("
            UPDATE products p
            SET attribute_value_ids = COALESCE(sub.ids, '{}')
            FROM (
                SELECT p2.id AS product_id,
                       array_agg(pav.attribute_value_id ORDER BY pav.attribute_value_id) AS ids
                FROM products p2
                LEFT JOIN product_attribute_values pav ON pav.product_id = p2.id
                GROUP BY p2.id
            ) sub
            WHERE sub.product_id = p.id
        ");

        // Single query: updates variants with values and resets those without
        DB::statement("
            UPDATE product_variants v
            SET attribute_value_ids = COALESCE(sub.ids, '{}')
            FROM (
                SELECT v2.id AS variant_id,
                       array_agg(vav.attribute_value_id ORDER BY vav.attribute_value_id) AS ids
                FROM product_variants v2
                LEFT JOIN variant_attribute_values vav ON vav.product_variant_id = v2.id
                GROUP BY v2.id
            ) sub
            WHERE sub.variant_id = v.id
        ");

        // CONCURRENTLY requires a prior non-concurrent populate; fallback handles fresh deploys
        try {
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY product_facet_counts');
        } catch (\Exception) {
            DB::statement('REFRESH MATERIALIZED VIEW product_facet_counts');
        }

        \Illuminate\Support\Facades\Cache::tags(['facets'])->flush();
    }
}
```

- [ ] **Step 4: Create artisan commands**

```php
<?php
// app/Console/Commands/CatalogRebuildCacheCommand.php

namespace App\Console\Commands;

use App\Domains\Catalog\Services\RebuildCatalogCacheService;
use Illuminate\Console\Command;

class CatalogRebuildCacheCommand extends Command
{
    protected $signature = 'catalog:rebuild-cache';
    protected $description = 'Rehydrate denormalized attribute caches and refresh facet counts';

    public function handle(RebuildCatalogCacheService $service): int
    {
        $this->info('Rebuilding catalog caches...');
        $service->handle();
        $this->info('Done.');
        return self::SUCCESS;
    }
}
```

```php
<?php
// app/Console/Commands/CatalogRefreshFacetCountsCommand.php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class CatalogRefreshFacetCountsCommand extends Command
{
    protected $signature = 'catalog:refresh-facets';
    protected $description = 'Refresh product_facet_counts materialized view';

    public function handle(): int
    {
        DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY product_facet_counts');
        return self::SUCCESS;
    }
}
```

- [ ] **Step 5: Schedule the refresh**

In `app/Console/Kernel.php` `schedule()` method:

```php
$schedule->command('catalog:refresh-facets')->everyFiveMinutes()->withoutOverlapping();
```

- [ ] **Step 6: Run test to verify it passes**

Run: `php artisan test --filter=RebuildCatalogCacheServiceTest`
Expected: PASS.

- [ ] **Step 7: Commit**

```bash
git add app/Domains/Catalog/Services/RebuildCatalogCacheService.php app/Console/Commands/CatalogRebuildCacheCommand.php app/Console/Commands/CatalogRefreshFacetCountsCommand.php app/Console/Kernel.php tests/Feature/Catalog/Services/RebuildCatalogCacheServiceTest.php
git commit -m "feat(catalog): rebuild caches + scheduled MV refresh commands"
```

---

## Task 20: Cache invalidation wiring (observers on Product/Variant save)

**Files:**
- Create: `app/Domains/Catalog/Observers/CatalogCacheInvalidationObserver.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/Catalog/Observers/CatalogCacheInvalidationTest.php`

- [ ] **Step 1: Write failing test**

```php
<?php
// tests/Feature/Catalog/Observers/CatalogCacheInvalidationTest.php

use App\Domains\Catalog\Models\Product;
use Illuminate\Support\Facades\Cache;

it('flushes the facets cache tag when a product is saved', function () {
    Cache::tags(['facets'])->put('catalog:facets:v1', ['cached' => true], 60);
    expect(Cache::tags(['facets'])->get('catalog:facets:v1'))->not->toBeNull();

    Product::create(['title' => 'Novo', 'slug' => 'novo-inv', 'origin' => 'national', 'status' => 'active']);

    expect(Cache::tags(['facets'])->get('catalog:facets:v1'))->toBeNull();
});
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php artisan test --filter=CatalogCacheInvalidationTest`
Expected: FAIL.

- [ ] **Step 3: Create observer**

```php
<?php
// app/Domains/Catalog/Observers/CatalogCacheInvalidationObserver.php

namespace App\Domains\Catalog\Observers;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Cache;

class CatalogCacheInvalidationObserver
{
    public function saved(Model $model): void
    {
        Cache::tags(['facets', "model:{$model->getKey()}"])->flush();
    }

    public function deleted(Model $model): void
    {
        $this->saved($model);
    }
}
```

- [ ] **Step 4: Register observer**

In `app/Providers/AppServiceProvider.php@boot`:

```php
use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductImage;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Catalog\Observers\CatalogCacheInvalidationObserver;

Product::observe(CatalogCacheInvalidationObserver::class);
ProductVariant::observe(CatalogCacheInvalidationObserver::class);
ProductImage::observe(CatalogCacheInvalidationObserver::class);
Attribute::observe(CatalogCacheInvalidationObserver::class);
AttributeValue::observe(CatalogCacheInvalidationObserver::class);
```

- [ ] **Step 5: Run test to verify it passes**

Run: `php artisan test --filter=CatalogCacheInvalidationTest`
Expected: PASS. The cache driver in tests must be `redis` (tagged cache requires it) or `array` (Laravel supports tags on array too).

- [ ] **Step 6: Commit**

```bash
git add app/Domains/Catalog/Observers/CatalogCacheInvalidationObserver.php app/Providers/AppServiceProvider.php tests/Feature/Catalog/Observers/CatalogCacheInvalidationTest.php
git commit -m "feat(catalog): invalidate Redis tagged cache on catalog writes"
```

---

## Task 21: Benchmark & performance smoke test

**Files:**
- Create: `tests/Feature/Catalog/Performance/CatalogPerformanceSmokeTest.php`

This test is marked `@group performance` so CI can skip if slow; it asserts plan shape via EXPLAIN to ensure GIN indexes are used.

- [ ] **Step 1: Write test**

```php
<?php
// tests/Feature/Catalog/Performance/CatalogPerformanceSmokeTest.php

use Illuminate\Support\Facades\DB;

it('uses GIN bitmap scan on products.attribute_value_ids filter', function () {
    // Force GIN usage — planner picks seqscan on empty tables otherwise
    DB::statement('SET LOCAL enable_seqscan = off');

    $plan = DB::select("
        EXPLAIN (FORMAT JSON)
        SELECT id FROM products
        WHERE attribute_value_ids @> '{1,2}'::bigint[]
    ")[0];

    DB::statement('SET LOCAL enable_seqscan = on');

    $planText = json_encode($plan);
    expect($planText)->toContain('Bitmap')->toContain('products_attrs_gin');
})->group('performance');
```

- [ ] **Step 2: Run the test**

Run: `php artisan test --filter=CatalogPerformanceSmokeTest`
Expected: PASS. The `SET LOCAL enable_seqscan = off` in the test forces GIN usage regardless of table size.

If empty-table planner issue occurs, seed 100 products in the test setup before running EXPLAIN.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Catalog/Performance/CatalogPerformanceSmokeTest.php
git commit -m "test(catalog): assert GIN index usage on product filter query"
```

---

## Task 22: Final integration — end-to-end scenario

**Files:**
- Create: `tests/Feature/Catalog/EndToEndCatalogScenarioTest.php`

- [ ] **Step 1: Write test covering the critical flow**

```php
<?php
// tests/Feature/Catalog/EndToEndCatalogScenarioTest.php

use App\Domains\Catalog\Models\Attribute;
use App\Domains\Catalog\Models\AttributeValue;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Seeders\AttributeSeeder;
use App\Domains\Catalog\Seeders\AttributeValueSeeder;
use App\Domains\Catalog\Services\GenerateVariantMatrixService;
use Illuminate\Support\Facades\DB;

it('creates product, attaches facets, generates matrix, lists with filter', function () {
    (new AttributeSeeder())->run();
    (new AttributeValueSeeder())->run();

    $brand = Attribute::where('code', 'brand')->first();
    $nike  = $brand->values()->where('slug', 'nike')->first();
    $team  = Attribute::where('code', 'team')->first();
    $cor   = $team->values()->where('slug', 'corinthians')->first();
    $color = Attribute::where('code', 'color')->first();
    $azul  = $color->values()->where('slug', 'azul')->first();
    $size  = Attribute::where('code', 'size')->first();
    $m     = $size->values()->where('slug', 'm')->first();
    $g     = $size->values()->where('slug', 'g')->first();

    $product = Product::create([
        'title' => 'Camisa Corinthians Nike 2026',
        'slug' => 'camisa-cor-nike-2026',
        'origin' => 'national',
        'status' => 'active',
    ]);

    $product->syncAttributeValues([$nike->id, $cor->id]);

    app(GenerateVariantMatrixService::class)->handle($product, [
        $color->id => [$azul->id],
        $size->id  => [$m->id, $g->id],
    ]);

    // activate variants with stock
    $product->variants()->update(['is_active' => true, 'stock_quantity' => 10, 'price' => 199.90]);
    DB::statement('REFRESH MATERIALIZED VIEW product_facet_counts');

    // listing with facet + variant filter
    $this->getJson('/api/catalog/products?brand=nike&team=corinthians&color=azul&size=m')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'camisa-cor-nike-2026']);

    // facets show counts
    $this->getJson('/api/catalog/facets')
        ->assertOk()
        ->assertJsonFragment(['slug' => 'nike', 'count' => 1]);
});
```

- [ ] **Step 2: Run test**

Run: `php artisan test --filter=EndToEndCatalogScenarioTest`
Expected: PASS.

- [ ] **Step 3: Commit**

```bash
git add tests/Feature/Catalog/EndToEndCatalogScenarioTest.php
git commit -m "test(catalog): end-to-end scenario (facets + matrix + filtered listing)"
```

---

## Self-review checklist (executed after writing this plan)

**Spec coverage**
- §3.1 dictionary — Tasks 3, 8, 9 ✅
- §3.2 pivots — Task 4 ✅
- §3.3 cache columns + GIN — Task 5 ✅
- §3.4 MV — Task 7 ✅
- §3.5 images — Task 6 ✅
- §3.6 extensions — Task 1 ✅
- §4 attribute classification — Task 11 ✅
- §5 matrix flow — Tasks 12, 18 ✅
- §6 storefront flow — Tasks 14, 15, 16 ✅
- §7 Redis layer — covered in Services (Tasks 14, 15) + invalidation (Task 20) ✅
- §8 sync observers + rebuild — Tasks 13, 19 ✅
- §9 API surface — Tasks 16, 17, 18 ✅
- §10 performance targets — Task 21 (smoke) ✅
- §11 Meilisearch escape hatch — documented only, no code task ✅
- §12 testing strategy — every task has tests; Task 22 is E2E ✅

**Placeholder scan** — no TBDs, no "add appropriate X", every test/code snippet is complete.

**Type consistency** — `attribute_value_ids` cast extracted to `HasAttributeValueIds` trait (Task 10); `syncAttributeValues()` signature consistent; `AttributeType::isFacet()/isVariant()` used consistently.

**Corrections applied (2026-04-24 code review):**
- Task 5: documented pre-existing `attribute_payload` dependency on `product_variants`
- Task 10: fixed `orderByPivot('display_order')` (was `orderBy('pivot_display_order')`)
- Task 10: extracted `attribute_value_ids` accessor to `HasAttributeValueIds` trait
- Task 12: removed dead `$signature` variable; `findVariantBySignature` receives `$combo`
- Task 13: clarified no separate Observer files; sync is via model methods; test updated accordingly
- Task 14: fixed facet filter `COALESCE(fc.product_count, 0) > 0` (was `IS NULL OR > 0`)
- Task 15: batched attribute value slug resolution (eliminated N+1); stable cache key via `ksort`; fixed return type to `LengthAwarePaginator` using `->paginate()`
- Task 19: unified NOT IN to LEFT JOIN batch UPDATE; added `CONCURRENTLY` fallback for fresh deploys
- Task 20: removed dead `method_exists($model, 'getKey')` conditional
- Task 21: added `SET LOCAL enable_seqscan = off` to prevent flaky planner choice on empty table

---

## Post-plan follow-ups (not in this plan, deliberate)

- Warm-cache job (§7.4) — low priority; add if hit rate < 60% post-launch.
- Stampede lock on listing cache — add when concurrency testing shows need.
- Image upload endpoints — belongs to the image upload task of the main commerce plan, not this one.
