# MM Sports — Commerce backend implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved commerce domain (catalog, marketing, wishlist, reviews, orders, user addresses, Correios shipping quotes, Asaas payments) in `mm-sports-api` with English field names, REST under `/api`, and Pest tests for critical paths.

**Architecture:** New Laravel domains under `app/Domains/Catalog`, `app/Domains/Marketing`, `app/Domains/Commerce`, `app/Domains/Reviews`, `app/Domains/Integrations` (service classes only, no extra HTTP layer). Models extend the shared `App\Domains\Shared\Models\BaseModel` (ULIDs + `TenantScope` trait: safe for models **not** listed in `config/cdf.php` `tenantModels`, i.e. no automatic `tenant_id` scoping for catalog). Migrations live in each domain’s `Migrations/` folder; `App\Providers\MigrationServiceProvider` already loads all `app/Domains/*/Migrations/*.php`. Routes: one file per area included from `routes/api.php`, mirroring `routes/domains/users.php` style.

**Tech Stack:** Laravel 11, PHP 8.2+, Pest, JWT `auth:api` where spec requires auth, Tymon JWTAuth, existing ACL patterns (`BaseController` + permissions where admin CRUD is added).

**Spec reference:** `docs/superpowers/specs/2026-04-23-mm-sports-commerce-backend-design.md`

---

## File map (created or touched)

| Area | Create / modify |
|------|-----------------|
| Catalog | `app/Domains/Catalog/Migrations/*`, `Models/{Product,ProductVariant,SizeChart,ProductPersonalizationOption}.php`, `Services/ProductCatalogService.php`, `Controllers/{ProductController,ProductVariantController}.php` (or single resource), `Requests/*` |
| Marketing | `app/Domains/Marketing/Migrations/*`, `Models/{Banner,Promotion,PromotionItem}.php`, `Controllers/BannerController.php` |
| Reviews | `app/Domains/Reviews/Migrations/*`, `Models/{ProductReview,WishlistItem}.php`, `Controllers/{ProductReviewController,WishlistController}.php` |
| Commerce | `app/Domains/Commerce/Migrations/*`, `Models/{UserAddress,Order,OrderItem}.php`, `Services/{CheckoutQuoteService,OrderService}.php`, `Controllers/{UserAddressController,OrderController,CheckoutQuoteController}.php` |
| Integrations | `app/Domains/Integrations/Services/CorreiosService.php`, `Asaas/AsaasClient.php` or `AsaasService.php`, `Http/Controllers/AsaasWebhookController.php` (namespace under Integrations) |
| Routes | `routes/domains/{catalog,marketing,reviews,commerce,integrations}.php` + `require` in `routes/api.php` |
| Config | `config/services.php` (asaas, correios, store CEP) |
| Tests | `tests/Feature/Domains/Catalog/*`, `Commerce/*`, etc. |
| App | `bootstrap/providers.php` if new provider needed (only if you introduce one) |

---

## Phase 0 — API wiring and configuration

### Task 0.1: Route includes and `api.php`

**Files:**
- Create: (empty for now) `routes/domains/catalog.php`, `routes/domains/marketing.php`, `routes/domains/reviews.php`, `routes/domains/commerce.php`, `routes/domains/integrations.php`
- Modify: `routes/api.php`

- [ ] **Step 1:** Add requires at bottom of `routes/api.php` (after existing requires):

```php
require __DIR__ . '/domains/catalog.php';
require __DIR__ . '/domains/marketing.php';
require __DIR__ . '/domains/reviews.php';
require __DIR__ . '/domains/commerce.php';
require __DIR__ . '/domains/integrations.php';
```

- [ ] **Step 2:** In each new `routes/domains/*.php` file, start with:

```php
<?php

use Illuminate\Support\Facades\Route;

// Routes added in later tasks
```

- [ ] **Step 3:** Run `php artisan route:list` — expect no new named routes until controllers exist; command must exit 0.

- [ ] **Step 4:** Commit:

```bash
git add routes/api.php routes/domains/catalog.php routes/domains/marketing.php routes/domains/reviews.php routes/domains/commerce.php routes/domains/integrations.php
git commit -m "chore(api): scaffold commerce domain route files"
```

### Task 0.2: `config/services.php` — store, Asaas, Correios

**Files:**
- Modify: `config/services.php`

- [ ] **Step 1:** Append return array keys (merge with existing, preserve other keys):

```php
    'mm_store' => [
        'origin_postal_code' => env('MM_STORE_POSTAL_CODE', '01310100'),
    ],
    'asaas' => [
        'api_key' => env('ASAAS_API_KEY'),
        'base_url' => env('ASAAS_BASE_URL', 'https://api.asaas.com/v3'),
        'webhook_token' => env('ASAAS_WEBHOOK_TOKEN'),
    ],
    'correios' => [
        'username' => env('CORREIOS_USERNAME'),
        'posting_card' => env('CORREIOS_POSTING_CARD'),
        'service_codes' => ['03220', '04014'], // PAC, SEDEX — adjust to contract
    ],
```

- [ ] **Step 2:** Add keys to `.env.example` (no real secrets): `MM_STORE_POSTAL_CODE=`, `ASAAS_API_KEY=`, `ASAAS_BASE_URL=`, `ASAAS_WEBHOOK_TOKEN=`, `CORREIOS_USERNAME=`, `CORREIOS_POSTING_CARD=`.

- [ ] **Step 3:** Commit:

```bash
git add config/services.php .env.example
git commit -m "chore: add Asaas, Correios, and store config keys"
```

---

## Phase 1 — Database: Catalog + Marketing + Reviews (migrations + models)

### Task 1.1: Single migration file — all commerce tables (ordered by FKs)

**Files:**
- Create: `app/Domains/Catalog/Migrations/2026_04_23_100000_create_mm_commerce_core_tables.php`

`users.id` is `ulid` (see `2019_12_14_000003_create_users_table.php`); all `user_id` columns use `foreignUlid` → `constrained('users')`.

`MigrationServiceProvider` already loads every `app/Domains/*/Migrations/*.php` file.

- [ ] **Step 1:** Create one migration. **`up()` table order (FK-safe):**  
  `size_charts` → `products` → `product_variants` → `user_addresses` → `orders` → `order_items` → `product_personalization_options` → `banners` → `promotions` → `promotion_items` → `wishlist_items` → `product_reviews`  
  `down()` must `dropIfExists` in **reverse** order (start with `product_reviews`, end with `size_charts`).

Use this as the **complete** `up(): void` body (condense `down()` in the same class by mirroring drops):

```php
    public function up(): void
    {
        Schema::create('size_charts', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->json('table_json');
            $table->timestamps();
        });

        Schema::create('products', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('title');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('origin');
            $table->boolean('allows_personalization')->default(false);
            $table->ulid('size_chart_id')->nullable();
            $table->string('status');
            $table->string('ncm')->nullable();
            $table->string('meta_title')->nullable();
            $table->string('meta_description')->nullable();
            $table->timestamps();
            $table->foreign('size_chart_id')->references('id')->on('size_charts')->nullOnDelete();
        });

        Schema::create('product_variants', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('sku')->unique();
            $table->decimal('price', 12, 2);
            $table->decimal('compare_at_price', 12, 2)->nullable();
            $table->unsignedInteger('stock_quantity')->default(0);
            $table->unsignedInteger('weight_grams')->nullable();
            $table->decimal('length_cm', 8, 2)->nullable();
            $table->decimal('width_cm', 8, 2)->nullable();
            $table->decimal('height_cm', 8, 2)->nullable();
            $table->json('attribute_payload')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('user_addresses', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('recipient_name');
            $table->string('postal_code', 9);
            $table->string('street');
            $table->string('number', 32);
            $table->string('complement')->nullable();
            $table->string('district');
            $table->string('city');
            $table->string('state', 2);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('discount_total', 12, 2)->default(0);
            $table->decimal('shipping_total', 12, 2)->default(0);
            $table->decimal('grand_total', 12, 2);
            $table->string('shipping_service_code')->nullable();
            $table->json('shipping_quote_json')->nullable();
            $table->json('shipping_address_snapshot');
            $table->string('correios_tracking_code')->nullable();
            $table->string('asaas_customer_id')->nullable();
            $table->string('asaas_payment_id')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->timestamp('shipped_at')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('order_id')->constrained('orders')->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->constrained('product_variants');
            $table->unsignedInteger('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->decimal('discount_amount', 12, 2)->default(0);
            $table->string('product_title_snapshot');
            $table->string('variant_label_snapshot');
            $table->json('personalization_snapshot')->nullable();
            $table->timestamps();
        });

        Schema::create('product_personalization_options', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->string('type');
            $table->string('label');
            $table->boolean('is_required')->default(false);
            $table->decimal('additional_price', 12, 2)->default(0);
            $table->unsignedInteger('max_length')->nullable();
            $table->json('options_json')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('banners', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('internal_title');
            $table->string('image_url');
            $table->string('destination_url');
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('device')->nullable();
            $table->timestamps();
        });

        Schema::create('promotions', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->string('name');
            $table->string('type');
            $table->decimal('value', 12, 2);
            $table->timestamp('starts_at');
            $table->timestamp('ends_at');
            $table->boolean('is_active')->default(true);
            $table->decimal('min_order_total', 12, 2)->nullable();
            $table->timestamps();
        });

        Schema::create('promotion_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('promotion_id')->constrained('promotions')->cascadeOnDelete();
            $table->foreignUlid('product_id')->nullable()->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->nullable()->constrained('product_variants')->cascadeOnDelete();
            $table->timestamps();
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('product_variant_id')->constrained('product_variants')->cascadeOnDelete();
            $table->timestamp('created_at')->useCurrent();
            $table->unique(['user_id', 'product_variant_id']);
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->ulid('id')->primary();
            $table->foreignUlid('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignUlid('product_id')->constrained('products')->cascadeOnDelete();
            $table->foreignUlid('order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            $table->string('moderation_status')->default('pending');
            $table->boolean('is_verified_purchase')->default(false);
            $table->text('store_reply')->nullable();
            $table->timestamp('store_replied_at')->nullable();
            $table->timestamps();
        });
    }
```

- [ ] **Step 2:** `php artisan migrate` — must exit **0**.

- [ ] **Step 3:** Commit the migration file.

### Task 1.2: Eloquent models (Catalog)

**Files:**
- Create: `app/Domains/Catalog/Models/Product.php`, `ProductVariant.php`, `SizeChart.php`, `ProductPersonalizationOption.php`

- [ ] **Step 1:** Implement `Product` with `protected $table = 'products'`, `protected $keyType = 'string'`, `public $incrementing = false`, `$fillable` matching columns, `casts` for `allows_personalization` bool, `origin` and `status` as string, relations: `sizeChart()`, `variants()`, `personalizationOptions()`.

- [ ] **Step 2:** `ProductVariant` — `belongsTo(Product::class)`, cast `attribute_payload` to array, `price` to decimal:2.

- [ ] **Step 3:** `SizeChart` — cast `table_json` to array.

- [ ] **Step 4:** `ProductPersonalizationOption` — `belongsTo(Product::class)`.

- [ ] **Step 5:** `php artisan tinker` quick check: `App\Domains\Catalog\Models\Product::count()` returns 0 without error.

- [ ] **Step 6:** Commit all four models.

**Repeat pattern** (Tasks 1.3–1.4) for Marketing and Reviews models — see spec columns; do not add `tenant_id` to these tables unless you extend multi-tenant later.

### Task 1.3: Eloquent models — Marketing

**Files:** `app/Domains/Marketing/Models/{Banner,Promotion,PromotionItem}.php`

- [ ] `Promotion` hasMany `items()` → `PromotionItem`. `PromotionItem` belongsTo `Promotion`, optional `Product`, optional `ProductVariant` (use `App\Domains\Catalog\Models\` imports).

- [ ] Commit.

### Task 1.4: Eloquent models — Commerce + Reviews

**Files:** `app/Domains/Commerce/Models/{UserAddress,Order,OrderItem}.php`, `app/Domains/Reviews/Models/{ProductReview,WishlistItem}.php`

- [ ] `UserAddress` `belongsTo` User. `Order` `hasMany` `items`, `belongsTo` User. `OrderItem` `belongsTo` Order, ProductVariant. `ProductReview` `belongsTo` User, Product, optional Order. `WishlistItem` `belongsTo` User, ProductVariant. Unique on wishlist at DB level + optional composite unique in model.

- [ ] `User` model: add `hasMany` relations methods `userAddresses`, `orders`, `wishlistItems` (optional but recommended for DX).

- [ ] Commit.

---

## Phase 2 — Public read API (products, banners) + feature tests

### Task 2.1: `GET /api/products` and `GET /api/products/{id}` (published only)

**Files:**
- Create: `app/Domains/Catalog/Controllers/ProductController.php` extending `BaseController` or `Controller` with index/show only (public)
- Create: `app/Domains/Catalog/Resources/` optional API Resources, or return plain arrays
- Modify: `routes/domains/catalog.php`

- [ ] **Step 1:** Failing test `tests/Feature/Domains/Catalog/ProductPublicTest.php`:

```php
<?php

use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductVariant;

beforeEach(function () {
    $this->artisan('migrate:fresh');
});

test('list published products returns 200 and json', function () {
    $p = Product::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'title' => 'Kit',
        'slug' => 'kit-1',
        'description' => null,
        'origin' => 'national',
        'allows_personalization' => false,
        'size_chart_id' => null,
        'status' => 'published',
        'ncm' => null,
        'meta_title' => null,
        'meta_description' => null,
    ]);
    ProductVariant::create([
        'id' => (string) \Illuminate\Support\Str::ulid(),
        'product_id' => $p->id,
        'sku' => 'SKU-1',
        'price' => 99.9,
        'compare_at_price' => null,
        'stock_quantity' => 1,
        'weight_grams' => 200,
        'length_cm' => null, 'width_cm' => null, 'height_cm' => null,
        'attribute_payload' => ['size' => 'M'],
        'is_active' => true,
    ]);

    $res = $this->getJson('/api/products');
    $res->assertOk()->assertJsonPath('data.0.title', 'Kit');
});
```

- [ ] **Step 2:** Run `cd /path/to/mm-sports-api && ./vendor/bin/pest tests/Feature/Domains/Catalog/ProductPublicTest.php` — **expect FAIL** (404 or no route).

- [ ] **Step 3:** Implement `ProductController@index` filtering `where('status', 'published')` with `variants` where `is_active`, register:

```php
Route::get('products', [App\Domains\Catalog\Controllers\ProductController::class, 'index']);
Route::get('products/{id}', [App\Domains\Catalog\Controllers\ProductController::class, 'show']);
```

- [ ] **Step 4:** Re-run Pest — **expect PASS**.

- [ ] **Step 5:** Commit.

**Note:** Enable `RefreshDatabase` in that test file with `uses(RefreshDatabase::class)` or `artisan migrate:fresh` in `beforeEach` as above — prefer `RefreshDatabase` trait in `Pest` for speed once parent TestCase is configured.

### Task 2.2: `GET /api/banners` (active + date window)

- [ ] Failing test: empty list returns `[]` or `data: []` — assert contract once chosen.

- [ ] `BannerController@index` public: filter `is_active`, `where` date range, `orderBy('sort_order')`.

- [ ] Route in `routes/domains/marketing.php`: `Route::get('banners', ...)`; commit.

---

## Phase 3 — Auth APIs: wishlist, reviews (create), addresses

### Task 3.1: Wishlist (variant-level)

**Spec:** `POST/DELETE/GET` wishlist; body uses `product_variant_id`.

- [ ] Feature test: authenticated user (use `User::factory` if exists, or `User::create` with required fields) adds variant to wishlist, `GET` returns 1 item, `DELETE` removes.

- [ ] `WishlistController` with `auth:api` middleware; methods `index`, `store` (ULID `product_variant_id`), `destroy`.

- [ ] `routes/domains/reviews.php`: `Route::middleware('auth:api')->group(... wishlist routes);` — *place wishlist in `reviews` route file or split `routes/domains/wishlist.php` if preferred*.

- [ ] Commit.

### Task 3.2: `POST /api/reviews` (user), moderation admin later

- [ ] Test: user creates `pending` review; **must** rate 1–5, link to `product_id`.

- [ ] `ProductReviewController@store` with `auth:api`. Optional `order_id` to set `is_verified_purchase` with validation (order belongs to user and contains line for product).

- [ ] `GET /api/products/{id}/reviews` public — only `moderation_status = approved` for public list.

- [ ] Commit.

### Task 3.3: `user_addresses` CRUD

- [ ] Test: user creates address with valid CEP/UF, cannot use `state` length ≠ 2.

- [ ] `UserAddressController` with validation rules (Laravel `Rule::in` for states).

- [ ] Commit.

---

## Phase 4 — Checkout quote + orders (stubs) + integration services

### Task 4.1: `POST /api/checkout/quote` (auth)

**Input (JSON):** `items` = `[{ "product_variant_id", "quantity }]`, `destination_postal_code` (8 digits, normalized), optional `user_address_id`.

- [ ] `CheckoutQuoteService` computes: line subtotals from `product_variants.price`, applies **one** active promotion (spec: “best first” or first match — **pick: highest discount** and document in code comment), default shipping `0.01` or call `CorreiosService` if config present.

- [ ] `CorreiosService::quote(...)` — if env missing, return structured stub `{ "price": 0, "eta_days": 0, "service_code": "STUB" }` so tests pass without API keys.

- [ ] Test: two items, valid CEP, assert JSON shape: `subtotal`, `discount_total`, `shipping_total`, `grand_total`, `lines`.

- [ ] Commit.

### Task 4.2: `POST /api/orders` — create pending order + call Asaas

- [ ] `OrderService::createFromCart` — persist `order`, `order_items` with snapshots, set `status` = `pending_payment`, call `AsaasService::createPayment` — if `ASAAS_API_KEY` empty, use **fake** that sets `asaas_payment_id` to `test_` + ulid and does not HTTP.

- [ ] Test: assert order in DB, status pending.

- [ ] Commit.

### Task 4.3: `POST /api/webhooks/asaas` (signed)

- [ ] `AsaasWebhookController`: validate `X-Asaas-Token` or custom header = `config('asaas.webhook_token')` when token set. Map payment received → set order `status` = `paid`, `paid_at` = now.

- [ ] Feature test: POST with valid token, mock order id in payload (shape per Asaas docs when integrating).

- [ ] Commit.

---

## Phase 5 — Admin / ACL (optional MVP)

- [ ] If admin must CRUD products in API: add permissions in `config/permission_list.php` and protect routes with `CheckPermission` like `UserController`. Otherwise **defer** to Filament/nova later and document in README.

- [ ] **Out of this plan** if not required for launch: full admin.

---

## Self-review (plan vs spec)

| Spec item | Plan tasks |
|-----------|------------|
| Products, variants, origin, size chart, personalization | Task 1.1–1.2, 2.1 |
| Banners + URL | 2.2 |
| Promotions + promotion_items | Task 1.1 models; quote logic 4.1 |
| Wishlist by variant | 1.1, 1.4, 3.1 |
| Reviews + verified | 1.1, 3.2 |
| Orders, snapshots, Brazil address | 1.1, 3.3, 4.1–4.2 |
| Correios | 0.2, 4.1 (stub + real) |
| Asaas | 0.2, 4.2–4.3 |
| English fields | Migrations + JSON keys in controllers |

**Task 1.1 note:** Merged migration uses FK-safe `up()` order; no further reordering required.

**Placeholder scan:** no TBD; admin deferred explicitly.

---

**Plan complete and saved to** `docs/superpowers/plans/2026-04-23-mm-sports-commerce-backend.md`.

**Two execution options:**

1. **Subagent-driven (recommended)** — dispatch a fresh subagent per task, review between tasks.  
2. **Inline execution** — run tasks in this session with checkpoints.

**Which approach do you want for implementation?**
