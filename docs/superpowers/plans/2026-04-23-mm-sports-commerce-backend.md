# MM Sports — Commerce backend implementation plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use `superpowers:subagent-driven-development` (recommended) or `superpowers:executing-plans` to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Implement the approved commerce domain (catalog, marketing, wishlist, reviews, orders, user addresses, Correios shipping quotes, Asaas payments) in `mm-sports-api` with English field names, REST under `/api`, and Pest tests for critical paths — **including full tracking** per spec §4.3 (`analytics_events`, `order_status_transitions`, `audit_logs`, `webhook_inbox` + `AnalyticsService` and hooks on all relevant flows).

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
| **Tracking** | `app/Domains/Tracking/Migrations/2026_04_23_100001_create_tracking_tables.php`, `Models/{AnalyticsEvent,OrderStatusTransition,AuditLog,WebhookInbox}.php`, `Services/{AnalyticsService,OrderStatusTracker,WebhookInboxService}.php`, `config/analytics.php` (event name allowlist), `Http/Controllers/AnalyticsEventController.php` (batch ingest) |
| Routes | `routes/domains/{catalog,marketing,reviews,commerce,integrations,tracking}.php` + `require` in `routes/api.php` |
| Config | `config/services.php` (asaas, correios, store CEP) |
| Tests | `tests/Feature/Domains/Catalog/*`, `Commerce/*`, etc. |
| App | `bootstrap/providers.php` if new provider needed (only if you introduce one) |

---

## Phase 0 — API wiring and configuration

### Task 0.1: Route includes and `api.php`

**Files:**
- Create: (empty for now) `routes/domains/catalog.php`, `routes/domains/marketing.php`, `routes/domains/reviews.php`, `routes/domains/commerce.php`, `routes/domains/integrations.php`, `routes/domains/tracking.php`
- Modify: `routes/api.php`

- [ ] **Step 1:** Add requires at bottom of `routes/api.php` (after existing requires):

```php
require __DIR__ . '/domains/catalog.php';
require __DIR__ . '/domains/marketing.php';
require __DIR__ . '/domains/reviews.php';
require __DIR__ . '/domains/commerce.php';
require __DIR__ . '/domains/integrations.php';
require __DIR__ . '/domains/tracking.php';
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
git add routes/api.php routes/domains/catalog.php routes/domains/marketing.php routes/domains/reviews.php routes/domains/commerce.php routes/domains/integrations.php routes/domains/tracking.php
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

### Task 1.1b: Tracking tables (depends on `users` + `orders` existing)

**Files:**
- Create: `app/Domains/Tracking/Migrations/2026_04_23_100001_create_tracking_tables.php` (new domain folder `app/Domains/Tracking/`)
- Create: `config/analytics.php`

- [ ] **Step 1:** Migration `up()` — create in this order:

1. `analytics_events` — columns: `id` (ulid), `user_id` nullable `foreignUlid` → `users`, `name` (string), `properties` (json nullable), `source` (string, default `api`), `request_id` (string nullable), `ip_address` (string nullable), `user_agent` (text nullable), `created_at` (no `updated_at` — append-only).
2. `order_status_transitions` — `id`, `foreignUlid('order_id')->constrained('orders')->cascadeOnDelete()`, `from_status` (string nullable), `to_status` (string), `source` (string), `meta` (json nullable), `created_at`.
3. `audit_logs` — `id`, `actor_user_id` nullable `foreignUlid` → `users` nullOnDelete(), `action`, `auditable_type`, `auditable_id` (ulid, not morph uuid — use `string` for id if needed), `old_values`/`new_values` json, `ip_address`, `user_agent` nullable, `created_at` only.
4. `webhook_inbox` — `id`, `provider` (string), `external_event_id` (string), **unique** index on `['provider', 'external_event_id']`, `payload_hash` nullable, `order_id` nullable `foreignUlid` → `orders` nullOnDelete(), `processing_result` (string), `error_message` (text nullable), `created_at`.

- [ ] **Step 2:** `config/analytics.php` return `['allowed_event_names' => [ 'product_viewed', 'product_list_viewed', 'banner_clicked', 'wishlist_added', 'wishlist_removed', 'review_submitted', 'checkout_quote_requested', 'order_created', 'payment_confirmed', 'order_shipped', 'search_executed' ] ]`.

- [ ] **Step 3:** `php artisan migrate` — exit 0.

- [ ] **Step 4:** Commit.

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

### Task 1.5: Tracking domain — models + `AnalyticsService` + `OrderStatusTracker`

**Files:**
- Create: `app/Domains/Tracking/Models/{AnalyticsEvent,OrderStatusTransition,AuditLog,WebhookInbox}.php`
- Create: `app/Domains/Tracking/Services/AnalyticsService.php` — `track(string $name, ?string $userId, array $properties, string $source = 'api', ?Request $request = null)` validates `$name` against `config('analytics.allowed_event_names')`, writes `analytics_events`.
- Create: `app/Domains/Tracking/Services/OrderStatusTracker.php` — `record(Order $order, ?string $from, string $to, string $source, ?array $meta): void` inside `DB::transaction` together with `$order->status = $to` (callers must use this helper only, not raw `update` on status).
- Create: `app/Domains/Tracking/Services/AuditLogger.php` — `log(?string $actorId, string $action, Model $model, ?array $old, ?array $new, ?Request $request)`.
- Create: `app/Domains/Tracking/Services/WebhookInboxService.php` — `claimOrIgnore(string $provider, string $externalEventId, Closure $process): void` uses `webhook_inbox` unique constraint; on duplicate external id, skip side effects.

- [ ] Unit or feature test: `OrderStatusTracker` creates a row in `order_status_transitions` when status changes; `AnalyticsService` rejects unknown `name`.

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

- [ ] **Tracking:** on successful `GET /api/products` list response, `AnalyticsService::track('product_list_viewed', auth id or null, ['category' => request], 'api', request)`.

- [ ] Route in `routes/domains/marketing.php`: `Route::get('banners', ...)`; commit.

### Task 2.3: `POST /api/analytics/events` (batch, optional auth)

- [ ] `AnalyticsEventController@store` in `routes/domains/tracking.php`: body `{ "events": [ { "name", "properties"?, "client_timestamp"? } ] }` — each row validated; max 50 per request; `user_id` from JWT if present.
- [ ] Throttle: `throttle:60,1` per IP.
- [ ] Feature test: two events, both persisted with allowed names.
- [ ] **Tracking (product show):** `ProductController@show` calls `product_viewed` with `product_id` / `product_variant` ids in `properties` when a variant is implied (if applicable).

- [ ] Commit.

---

## Phase 3 — Auth APIs: wishlist, reviews (create), addresses

### Task 3.1: Wishlist (variant-level)

**Spec:** `POST/DELETE/GET` wishlist; body uses `product_variant_id`.

- [ ] Feature test: authenticated user (use `User::factory` if exists, or `User::create` with required fields) adds variant to wishlist, `GET` returns 1 item, `DELETE` removes.

- [ ] `WishlistController` with `auth:api` middleware; methods `index`, `store` (ULID `product_variant_id`), `destroy`.

- [ ] **Tracking:** `store` → `AnalyticsService::track('wishlist_added', ...)`; `destroy` → `wishlist_removed` with `product_variant_id` in `properties`.

- [ ] `routes/domains/reviews.php`: `Route::middleware('auth:api')->group(... wishlist routes);` — *place wishlist in `reviews` route file or split `routes/domains/wishlist.php` if preferred*.

- [ ] Commit.

### Task 3.2: `POST /api/reviews` (user), moderation admin later

- [ ] Test: user creates `pending` review; **must** rate 1–5, link to `product_id`.

- [ ] `ProductReviewController@store` with `auth:api`. Optional `order_id` to set `is_verified_purchase` with validation (order belongs to user and contains line for product).

- [ ] **Tracking:** after create → `review_submitted` with `product_id`, `rating` in `properties`.

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

- [ ] **Tracking:** successful quote → `checkout_quote_requested` with `postal_code` (5 digits + mask only, no full PII if policy requires), `line_count` in `properties`.

- [ ] `CorreiosService::quote(...)` — if env missing, return structured stub `{ "price": 0, "eta_days": 0, "service_code": "STUB" }` so tests pass without API keys.

- [ ] Test: two items, valid CEP, assert JSON shape: `subtotal`, `discount_total`, `shipping_total`, `grand_total`, `lines`.

- [ ] Commit.

### Task 4.2: `POST /api/orders` — create pending order + call Asaas

- [ ] `OrderService::createFromCart` — persist `order`, `order_items` with snapshots, set `status` = `pending_payment` via `OrderStatusTracker` (`from` null, `to` = `pending_payment`, `source` = `system`, `meta` with cart summary hash). Then call `AsaasService::createPayment` — if `ASAAS_API_KEY` empty, use **fake** that sets `asaas_payment_id` to `test_` + ulid and does not HTTP.

- [ ] **Tracking:** `order_created` with `order_id` in `properties`; do **not** set status with raw Eloquent on `Order` in this code path (use `OrderStatusTracker` only).

- [ ] Test: assert order in DB, status pending, **and** 1 `order_status_transitions` row + 1 `analytics_events` with `order_created`.

- [ ] Commit.

### Task 4.3: `POST /api/webhooks/asaas` (signed) + idempotency

- [ ] `AsaasWebhookController`: **first** `WebhookInboxService::claimOrIgnore('asaas', $idFromPayload, fn () => ...)`; inside closure: validate `X-Asaas-Token` (or Asaas-suggested header) = `config('asaas.webhook_token')` when token set. Map payment received → `OrderStatusTracker` to `paid` with `source` = `asaas_webhook` and `meta` including event id. Set `webhook_inbox.processing_result` to `processed` or `failed`.

- [ ] On duplicate `external_event_id`, closure not run; inbox row `ignored` with no double payment application.

- [ ] **Tracking:** `payment_confirmed` with `order_id` in `properties` when transition to `paid` succeeds (same transaction as transition).

- [ ] Feature test: POST with valid token, mock order id in payload; second identical POST does not change totals twice (assert one transition to `paid`).

- [ ] Commit.

### Task 4.4: `GET /api/orders/{id}/timeline` (auth, owner or admin)

- [ ] Return `order_status_transitions` ordered by `created_at` asc + optional `correios_tracking_code` on parent order in envelope.

- [ ] **Tracking:** N/A (read path).

- [ ] Commit.

---

## Phase 5 — Admin / ACL (optional MVP)

- [ ] If admin must CRUD products in API: add permissions in `config/permission_list.php` and protect routes with `CheckPermission` like `UserController`. Otherwise **defer** to Filament/nova later and document in README.

- [ ] **Tracking:** on every future admin `store`/`update`/`destroy` for `Product`, `Banner`, `Promotion`, and review moderation, call `AuditLogger` with `source` in `new_values` / actor; ensure `analytics_events` is **not** required for read-only admin actions.

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
| Tracking (§4.3) | 1.1b, 1.5, 2.3, 2.1–2.2 bullets, 3.1–3.2, 4.1–4.4, Phase 5 admin audit |

**Task 1.1 note:** Merged migration uses FK-safe `up()` order; no further reordering required.

**Placeholder scan:** no TBD; admin deferred explicitly.

---

**Plan complete and saved to** `docs/superpowers/plans/2026-04-23-mm-sports-commerce-backend.md`.

**Two execution options:**

1. **Subagent-driven (recommended)** — dispatch a fresh subagent per task, review between tasks.  
2. **Inline execution** — run tasks in this session with checkpoints.

**Which approach do you want for implementation?**
