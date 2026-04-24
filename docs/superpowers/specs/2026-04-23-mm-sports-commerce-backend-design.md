# MM Sports — Commerce backend design (approved)

**Status:** approved for implementation planning  
**Date:** 2026-04-23  
**Scope:** Single store (MM), **Brazil-only** shipping. Products may be **national** or **imported**; **import taxes are included in the displayed unit price** (no separate breakdown line in MVP).  
**Conventions:** All **database columns and API JSON keys in English**. User-facing copy remains Portuguese in the app.

## 1. Architecture

- **Style:** Monolith with **domain modules** under `app/Domains/` (aligned with existing Auth/ACL).
- **Suggested domains:** `Catalog` (products, variants, size charts, personalization), `Marketing` (banners, promotions), `Commerce` (orders, order items, addresses), `Reviews`, `Integrations` (Correios, Asaas; thin adapters + webhooks).
- **IDs:** Prefer **ULID** for public-facing resources where existing project patterns use `HasUlid`.

## 2. Business rules (fixed decisions)

| Topic | Decision |
|-------|----------|
| Market | **Brazil only**; order addresses are domestic (CEP/UF). |
| Origin | `Product.origin`: `national` \| `imported` — affects messaging/compliance; **price is still one number per variant** (taxes embedded). |
| Pricing | **Single sale price** on variant; no separate tax breakdown in MVP. |
| Wishlist | **Per variant** — `wishlist_items` reference `product_variant_id` (not product alone). |
| Field language | **English** for schema/API/code identifiers. |

## 3. Data model (tables & key fields)

### 3.1 Catalog

**`products`**

| Column | Type | Notes |
|--------|------|--------|
| id | ulid / bigint | PK |
| title | string | |
| slug | string | unique |
| description | text | nullable |
| origin | enum: `national`, `imported` | |
| allows_personalization | boolean | default false |
| size_chart_id | fk nullable | → `size_charts` |
| status | enum: `draft`, `published`, `archived` | |
| ncm | string nullable | fiscal metadata when available |
| meta_title, meta_description | string nullable | SEO |
| created_at, updated_at | timestamps | |

**`product_variants`**

| Column | Type | Notes |
|--------|------|--------|
| id | ulid / bigint | PK |
| product_id | fk | → `products` |
| sku | string | unique (scoped or global per project rule) |
| price | decimal(12,2) | **display price; import duties included** |
| compare_at_price | decimal nullable | “was” price |
| stock_quantity | int | default 0 |
| weight_grams | int nullable | Correios / cubage |
| length_cm, width_cm, height_cm | decimal nullable | |
| attribute_payload | json nullable | e.g. `{"size":"M","color":"red"}` if not normalized |
| is_active | boolean | default true |
| created_at, updated_at | timestamps | |

**`size_charts`**

| Column | Type | Notes |
|--------|------|--------|
| id | pk | |
| name | string | e.g. “Adult jersey” |
| table_json | json | structured rows/columns (validated in app) |
| created_at, updated_at | timestamps | |

**`product_personalization_options`**

| Column | Type | Notes |
|--------|------|--------|
| id | pk | |
| product_id | fk | |
| type | enum: `short_text`, `long_text`, `select`, `number` | extensible |
| label | string | |
| is_required | boolean | |
| additional_price | decimal default 0 | |
| max_length | int nullable | for text |
| options_json | json nullable | for `select`: allowed values |
| sort_order | int | |
| created_at, updated_at | timestamps | |

### 3.2 Marketing

**`banners`**

| Column | Type | Notes |
|--------|------|--------|
| id | pk | |
| internal_title | string | admin only |
| image_url | string | e.g. S3 URL |
| destination_url | string | full URL for click |
| sort_order | int | |
| is_active | boolean | |
| starts_at, ends_at | timestamp nullable | scheduled visibility |
| device | enum nullable | `all`, `desktop`, `mobile` if needed |
| created_at, updated_at | timestamps | |

**`promotions`**

| Column | Type | Notes |
|--------|------|--------|
| id | pk | |
| name | string | |
| type | enum | `percent`, `fixed_amount` (extend later) |
| value | decimal | percent or fixed in BRL depending on type |
| starts_at, ends_at | timestamp | |
| is_active | boolean | |
| min_order_total | decimal nullable | |
| created_at, updated_at | timestamps | |

**`promotion_items`** (scope: which products/variants participate)

| Column | Type | Notes |
|--------|------|--------|
| id | pk | |
| promotion_id | fk | |
| product_id | fk nullable | if whole product |
| product_variant_id | fk nullable | if specific SKU |
| created_at, updated_at | timestamps | |

*Rule:* at least one of `product_id` or `product_variant_id` set; validate in app.

### 3.3 Social / UGC

**`product_reviews`**

| Column | Type | Notes |
|--------|------|--------|
| id | pk | |
| user_id | fk | |
| product_id | fk | |
| order_id | fk nullable | to mark verified purchase |
| rating | tinyInteger | 1–5 |
| title | string nullable | |
| body | text | |
| moderation_status | enum: `pending`, `approved`, `rejected` | |
| is_verified_purchase | boolean | default false |
| store_reply | text nullable | |
| store_replied_at | timestamp nullable | |
| created_at, updated_at | timestamps | |

**`wishlist_items`**

| Column | Type | Notes |
|--------|------|--------|
| id | pk | |
| user_id | fk | |
| product_variant_id | fk | **variant-level wishlist** |
| created_at | timestamp | |
| | | unique(`user_id`, `product_variant_id`) |

### 3.4 Commerce (orders)

**`orders`**

| Column | Type | Notes |
|--------|------|--------|
| id | ulid / bigint | |
| user_id | fk | |
| status | enum | `pending_payment`, `paid`, `processing`, `shipped`, `delivered`, `cancelled`, … |
| subtotal, discount_total, shipping_total, grand_total | decimal | |
| shipping_service_code | string nullable | Correios service (e.g. PAC, SEDEX) |
| shipping_quote_json | json nullable | snapshot: price, eta, weight |
| shipping_address_snapshot | json | **Brazil** address at checkout time |
| correios_tracking_code | string nullable | |
| asaas_customer_id | string nullable | |
| asaas_payment_id | string nullable | |
| created_at, updated_at, paid_at, shipped_at | timestamps | |

**`order_items`**

| Column | Type | Notes |
|--------|------|--------|
| id | pk | |
| order_id | fk | |
| product_variant_id | fk | |
| quantity | int | |
| unit_price | decimal | snapshot at purchase |
| discount_amount | decimal default 0 | from promotion |
| product_title_snapshot, variant_label_snapshot | string | for receipts |
| personalization_snapshot | json nullable | user choices at order time |
| created_at, updated_at | timestamps | |

**`user_addresses` (or reuse existing addresses table)**

- All fields in English: `recipient_name`, `postal_code`, `street`, `number`, `complement`, `district`, `city`, `state` (2-letter), `is_default`, etc.  
- Validate **Brazil** (CEP format, state).

## 4. Integrations

### 4.1 Correios (domestic)

- **Quote:** `CorreiosService` — input: origin CEP (store), dest CEP, weight/dimensions (from line items or per-variant), service list.  
- **Persistence:** store chosen quote on `orders.shipping_*` and **tracking** when posted.

### 4.2 Asaas (payments)

- Create/link **customer** from `users`.  
- Create **payment** for `grand_total` on checkout; **webhooks** update `orders.status` (`paid`, failed, cancelled).  
- **Never** store card data.

## 5. API surface (high level, English resources)

- `GET/POST/DELETE /api/wishlist` — item keyed by `product_variant_id`.  
- `GET /api/banners` — public active banners with `image_url`, `destination_url`.  
- `GET/POST /api/reviews` — moderation for admin.  
- `POST /api/checkout/quote` — shipping + promotions preview.  
- `POST /api/orders` — creates order + Asaas charge flow.

## 6. Out of scope (MVP)

- International shipping or multi-currency.  
- Detailed tax line-item breakdown on invoice UI (only embedded price).  
- Advanced promotion stacking rules (define simple rule: e.g. best single promotion first).

## 7. Self-review

- **Placeholders:** None intended; ambiguities pushed to “MVP / later”.  
- **Consistency:** `imported` origin + single `price` on variant; wishlist on `product_variant_id`.  
- **Scope:** One implementation plan can follow this doc; large features (full fiscal NF-e) are noted as future.

---

*Next step: implementation plan (tasks per domain) after stakeholder sign-off on this file.*
