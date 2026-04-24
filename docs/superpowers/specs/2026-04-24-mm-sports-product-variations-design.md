# MM Sports — Product variations & faceted catalog design (approved)

**Status:** approved for implementation planning
**Date:** 2026-04-24
**Scope:** Catalog attributes (facets + variant axes), SKU matrix generation, faceted listing, PDP variant resolution, performance layer (Postgres GIN + Redis cache).
**Complements:** `2026-04-23-mm-sports-commerce-backend-design.md` (refines §3.1 Catalog).
**Conventions:** DB columns and API JSON keys in English; user-facing copy in PT-BR.

---

## 1. Goals & non-goals

**Goals**
- Model a **flexible** attribute system: each product declares which attributes are facets and which define SKU matrix axes.
- Support 12+ current attributes (Tipo de Produto, Marca, Cor, Tamanho, Idade, Time, Esporte, Material, Gola, Bolso, Manga, Aba) and grow without schema changes.
- Serve faceted listings + PDP in **p95 < 50ms** on 100k products.
- Stay on **Postgres + Redis** (no Meilisearch/Elastic in MVP) while keeping a documented escape hatch.

**Non-goals**
- External search engine (Meilisearch/Typesense) — deferred to Phase 3 with explicit trigger conditions.
- Multi-store / multi-locale catalog (single store, PT-BR).
- Dynamic per-tenant attribute sets.

---

## 2. Key decisions

| Decision | Choice | Reason |
|---|---|---|
| Attribute modeling | **Hybrid**: dictionary + pivots + denormalized arrays | Integrity + query speed |
| Variant creation UX | **Matrix-generated** (eixos + valores → SKUs) | 95% of sports e-com fits |
| Product-level vs SKU-level | `attributes.type` = `facet | variant | both` | Same table, different role per product |
| Images | Attached to `attribute_value_id` (nullable) | Fotos por cor é padrão em moda/esporte |
| Performance model | Postgres GIN arrays + MV + Redis cache layer | Meets p95 < 50ms on 100k; zero extra services |
| Source of truth | Normalized pivots | Arrays/MV are rebuildable caches |
| Cache consistency | Eventually consistent (seconds) | Acceptable for catalog; stock is separate |

---

## 3. Data model

All tables live under `app/Domains/Catalog/` (per CodifyTech DDD). IDs follow existing project conventions (ULIDs for public-facing; bigint PK allowed for pivot/internal).

### 3.1 Attribute dictionary

**`attributes`**

| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| code | string unique | machine key, e.g. `color`, `size`, `brand`, `team` |
| label | string | UI label (PT-BR), e.g. "Cor", "Marca" |
| type | enum (`facet`,`variant`,`both`) | drives where it applies |
| input_type | enum (`select`,`multiselect`,`text`,`swatch`) | admin UI hint |
| is_filterable | boolean default true | shows in storefront sidebar |
| display_order | int default 0 | |
| created_at, updated_at | timestamps | |

**`attribute_values`**

| Column | Type | Notes |
|---|---|---|
| id | bigint pk | |
| attribute_id | fk → attributes | |
| value | string | "Azul", "M", "Nike", "Corinthians" |
| slug | string | URL-safe |
| metadata | jsonb nullable | e.g. `{"hex":"#0000FF"}` for color swatches |
| display_order | int default 0 | |
| | | **unique(attribute_id, slug)** |

### 3.2 Product ↔ attribute relations

**`product_attribute_values`** — facet values attached to a product (Marca, Time, Esporte, Material, Gola, Bolso, Manga, Aba, Tipo de Produto)

| Column | Type | Notes |
|---|---|---|
| product_id | fk | |
| attribute_value_id | fk | |
| | | primary key (product_id, attribute_value_id) |

**`product_variant_axes`** — declares which attributes define the SKU matrix of a product

| Column | Type | Notes |
|---|---|---|
| product_id | fk | |
| attribute_id | fk | attribute with `type` ∈ (`variant`,`both`) |
| display_order | int default 0 | ordering in admin matrix & PDP selector |
| | | unique(product_id, attribute_id) |

**`variant_attribute_values`** — the exact combination of each SKU

| Column | Type | Notes |
|---|---|---|
| product_variant_id | fk | |
| attribute_id | fk | |
| attribute_value_id | fk | |
| | | unique(product_variant_id, attribute_id) |

### 3.3 Denormalized cache columns (performance layer)

**`products`** — add:

```sql
ALTER TABLE products
  ADD COLUMN attribute_value_ids bigint[] DEFAULT '{}' NOT NULL,
  ADD COLUMN search_tsv tsvector
    GENERATED ALWAYS AS (
      to_tsvector('portuguese', coalesce(name,'') || ' ' || coalesce(description,''))
    ) STORED;

CREATE INDEX products_attrs_gin    ON products USING GIN (attribute_value_ids);
CREATE INDEX products_search_gin   ON products USING GIN (search_tsv);
CREATE INDEX products_name_trgm    ON products USING GIN (name gin_trgm_ops);
```

**`product_variants`** — add (extending existing `attribute_payload jsonb` from the commerce spec):

```sql
ALTER TABLE product_variants
  ADD COLUMN attribute_value_ids bigint[] DEFAULT '{}' NOT NULL;

CREATE INDEX pv_attrs_gin    ON product_variants USING GIN (attribute_value_ids);
CREATE INDEX pv_payload_gin  ON product_variants USING GIN (attribute_payload jsonb_path_ops);
```

`attribute_payload` remains (from the commerce spec) as a human-readable denormalized map, e.g. `{"color":"Azul","size":"M"}`, for display without joins.

### 3.4 Facet counts materialized view

```sql
CREATE MATERIALIZED VIEW product_facet_counts AS
SELECT av.attribute_id,
       av.id AS attribute_value_id,
       COUNT(DISTINCT p.id) AS product_count
FROM attribute_values av
JOIN product_attribute_values pav ON pav.attribute_value_id = av.id
JOIN products p ON p.id = pav.product_id AND p.is_active
GROUP BY 1, 2;

CREATE UNIQUE INDEX product_facet_counts_pk
  ON product_facet_counts (attribute_id, attribute_value_id);
```

Refresh `CONCURRENTLY` every 5 minutes via scheduler, or on-demand after bulk imports.

### 3.5 Images attached to attribute values

**`product_images`** — add:

```sql
ALTER TABLE product_images
  ADD COLUMN attribute_value_id bigint NULL REFERENCES attribute_values(id);

CREATE INDEX product_images_attr_value ON product_images (product_id, attribute_value_id);
```

- `attribute_value_id = NULL` → galeria geral do produto
- `attribute_value_id = <cor>` → imagens daquela variação de cor (padrão para `color`)

### 3.6 Required Postgres extensions

```sql
CREATE EXTENSION IF NOT EXISTS pg_trgm;
CREATE EXTENSION IF NOT EXISTS btree_gin;
CREATE EXTENSION IF NOT EXISTS unaccent;
```

---

## 4. Classification of MM Sports' current attributes

| Atributo | `attributes.type` | Rationale |
|---|---|---|
| Tipo de Produto | `facet` | Classifica o produto; não gera SKU |
| Marca | `facet` | Um produto = uma marca |
| Time | `facet` | Classificação |
| Esporte | `facet` | Classificação |
| Material | `facet` | Ficha técnica |
| Gola | `facet` | Ficha técnica |
| Bolso | `facet` | Ficha técnica |
| Manga | `facet` | Ficha técnica |
| Aba | `facet` | Ficha técnica (bonés) |
| Idade | `facet` | Faixa (Infantil/Juvenil/Adulto). Se virar tamanho numérico real, reclassificar como `variant` |
| **Cor** | **`variant`** | Gera SKU; troca imagens |
| **Tamanho** | **`variant`** | Gera SKU |

Seeders em `Catalog/Seeders/AttributeSeeder.php` e `AttributeValueSeeder.php`.

---

## 5. Admin flow — matrix-generated variants

1. Admin cadastra o produto com campos fixos (name, description, origin, price-base etc.) e seleciona **facetas** (campos de `attributes.type ∈ (facet, both)`).
2. Admin declara **eixos de variação** em `product_variant_axes` (ex.: `color`, `size`).
3. Admin seleciona valores por eixo: Cor = {Azul, Vermelho}, Tamanho = {P, M, G}.
4. Service `Catalog\Services\GenerateVariantMatrixService@handle($product, $axes, $valuesByAxis)`:
   - Gera produto cartesiano (2×3 = 6 combinações)
   - Cria `product_variants` faltantes (idempotente; combinações já existentes preservam preço/estoque)
   - Popula `variant_attribute_values`
   - Dispara observer que recomputa `attribute_value_ids` array e `attribute_payload` JSON
5. Admin edita o grid: SKU code, preço, `compare_at_price`, estoque, peso, `is_active`.
6. Upload de imagens: admin escolhe "galeria" (NULL) ou um valor de Cor.

**Edge cases:**
- Remover valor do eixo: variantes órfãs são **desativadas** (`is_active=false`), nunca deletadas, para preservar histórico de pedidos.
- Adicionar novo valor: gera novas variantes inativas por padrão (admin ativa após preencher preço/estoque).

---

## 6. Storefront flow — facets & listing

### 6.1 Sidebar de filtros

`GET /api/catalog/facets?category={slug}`

Lê da MV `product_facet_counts` joinada com `attributes` + `attribute_values` filtrando por `attributes.is_filterable` e `product_count > 0`.

Resposta:

```json
{
  "facets": [
    { "code": "brand", "label": "Marca", "input_type": "multiselect", "values": [
        { "id": 10, "label": "Nike", "slug": "nike", "count": 45 }
    ]},
    { "code": "color", "label": "Cor", "input_type": "swatch", "values": [
        { "id": 88, "label": "Azul", "slug": "azul", "metadata": {"hex":"#0000FF"}, "count": 12 }
    ]}
  ]
}
```

### 6.2 Listagem filtrada

`GET /api/catalog/products?brand=nike,adidas&team=corinthians&color=azul&size=m&q=camisa`

Service resolve slugs → IDs, particiona em facetas (product-level) vs. variante (variant-level):

```sql
SELECT p.* FROM products p
WHERE p.is_active
  AND p.attribute_value_ids @> ARRAY[:facet_ids]::bigint[]
  AND (:has_text = false OR p.search_tsv @@ plainto_tsquery('portuguese', :q))
  AND (:has_variant_filter = false OR EXISTS (
    SELECT 1 FROM product_variants v
    WHERE v.product_id = p.id
      AND v.is_active AND v.stock > 0
      AND v.attribute_value_ids @> ARRAY[:variant_ids]::bigint[]
  ))
ORDER BY p.sold_count DESC, p.id DESC
LIMIT :limit OFFSET :offset;
```

### 6.3 PDP (product detail page)

`GET /api/catalog/products/{slug}` retorna:

- Produto + facetas (atributos `type=facet`)
- `variant_axes` ordenados
- Matriz de `variants` com `attribute_payload` (já denormalizado, sem join extra)
- `images` agrupadas por `attribute_value_id` (cor) + galeria geral

Seleção de variante no client resolve por `attribute_value_ids @> [cor_id, size_id]`.

---

## 7. Cache layer (Redis)

### 7.1 Keys & TTLs

| Chave | TTL | Conteúdo |
|---|---|---|
| `catalog:facets:v1` | 5 min | Sidebar global |
| `catalog:facets:v1:cat:{slug}` | 5 min | Sidebar por categoria |
| `catalog:list:{sha1(filters+page)}` | 60 s | Página de listagem |
| `catalog:pdp:{product_slug}` | 5 min | PDP completo |
| `catalog:variant-matrix:{product_id}` | 5 min | Matriz para admin/PDP |
| `catalog:counts:filtered:{sha1(filters)}` | 30 s | Contagens dinâmicas de facet refinado |

### 7.2 Invalidação

Cache tags via `Illuminate\Cache\TaggedCache` (Redis store):

- Tag `product:{id}` — purga em save/delete do produto.
- Tag `facets` — purga em save/delete de `attributes`, `attribute_values`, e qualquer `product` (pois altera contagens).
- Tag `category:{id}` — purga listagens da categoria.

Observer `ProductObserver`:

```php
public function saved(Product $p): void {
    Cache::tags(["product:{$p->id}", "facets"])->flush();
    foreach ($p->categories as $c) {
        Cache::tags("category:{$c->id}")->flush();
    }
}
```

Observers análogos em `Attribute`, `AttributeValue`, `ProductVariant`, `ProductImage`.

### 7.3 Stampede protection

Listagens usam lock curto para evitar thundering herd no miss:

```php
Cache::lock("lock:list:{$hash}", 3)->block(1, function () use ($hash) {
    return Cache::remember("catalog:list:{$hash}", 60, fn() => $service->query(...));
});
```

### 7.4 Warm cache

Job `WarmCatalogCacheJob` (scheduler a cada 10 min + pós-deploy):
- Facets globais + top N categorias
- Top 50 listagens mais acessadas (tracked via analytics)
- PDPs dos top 100 produtos por `sold_count`

---

## 8. Sync jobs & observers (source-of-truth → caches)

| Evento | Ação |
|---|---|
| Save em `product_attribute_values` (attach/detach) | Recomputa `products.attribute_value_ids` |
| Save em `variant_attribute_values` | Recomputa `product_variants.attribute_value_ids` + `attribute_payload` |
| Save em `Product`/`AttributeValue` | Purga tags Redis relevantes |
| Save em qualquer dos anteriores | `product_facet_counts` MV refresh (debounced, max 1/min via `REFRESH MATERIALIZED VIEW CONCURRENTLY`) |

Comando `php artisan catalog:rebuild-cache` rehidrata arrays e payloads em massa (uso pós-migração/correção). Exposto também como job `RebuildCatalogCacheJob` com chunking.

---

## 9. API surface (novo/alterado)

| Método | Rota | Finalidade |
|---|---|---|
| GET | `/api/catalog/facets` | Sidebar de filtros (com contagens) |
| GET | `/api/catalog/products` | Listagem filtrada + busca textual |
| GET | `/api/catalog/products/{slug}` | PDP com matriz de variantes |
| GET | `/admin/attributes` | CRUD de atributos |
| POST/PUT/DELETE | `/admin/attributes/{id}` | |
| GET | `/admin/attributes/{id}/values` | CRUD de valores |
| POST/PUT/DELETE | `/admin/attribute-values/{id}` | |
| POST | `/admin/products/{id}/variant-matrix` | Gera matriz de SKUs |
| PATCH | `/admin/products/{id}/variants/{vid}` | Ajusta preço/estoque/ativo |

Todas as rotas seguem o padrão CodifyTech DDD (Controllers orquestram; Services executam; FormRequests validam).

---

## 10. Performance targets

| Operação | Cache miss (Postgres) | Cache hit (Redis) | p95 alvo |
|---|---|---|---|
| `/facets` | 15-30 ms | < 2 ms | **< 10 ms** |
| `/products` listagem | 10-30 ms | < 3 ms | **< 30 ms** |
| `/products/{slug}` PDP | 5-15 ms | < 2 ms | **< 15 ms** |
| Facet counts refinados | 20-50 ms | — | < 80 ms |

Em catálogo de 100k produtos com hit rate esperado de 80-95%. Monitorar via APM (New Relic / OpenTelemetry).

---

## 11. Escape hatch — Phase 3 (Meilisearch)

**Não faz parte do MVP.** Documentado para evitar retrabalho futuro.

Gatilhos de migração:
- Catálogo > 100k produtos
- p95 de listagem > 100 ms sustentado com cache quente
- Requisitos de busca textual avançada (autocomplete com typo-tolerance, ranking configurável, highlighting, multi-idioma)

Estratégia quando acionado:
- Postgres mantém source of truth + writes
- Meilisearch indexa documentos planos (produto + facetas + variantes resumidas) via job `SyncProductToSearchJob` em save
- Rotas `/api/catalog/products` e `/api/catalog/facets` passam a ler do Meilisearch; Redis vira cache opcional
- Migração estimada em ~2 semanas

---

## 12. Testing strategy

- **Unit** (Services): `GenerateVariantMatrixService`, `ResolveProductFiltersService`, `RebuildCatalogCacheService`
- **Feature** (HTTP): rotas públicas e admin cobertas, incluindo casos de filtros combinados e variantes sem estoque
- **DB** (pgsql real, sem mock): validar que `attribute_value_ids` fica sincronizado após attach/detach; que a MV atualiza
- **Cache**: asserções com `Cache::shouldReceive` + teste de invalidação por tag
- **Seeders de fixtures**: 12 atributos, ~60 valores, 200 produtos, 1000 variantes para testes de listagem

---

## 13. Open questions (post-approval, pre-plan)

Nenhuma bloqueante. Itens deixados explícitos para Fase 2+:

- Ranking de listagem mais sofisticado (boost por estoque, recência, margem) — hoje só `sold_count DESC`.
- Facets multiselect com lógica OR dentro da faceta e AND entre facetas — implementação do array já permite; UI define.
- Contagens "refinadas" em tempo real (post-filter) — disponível via query, decidir se ligadas no MVP.

---

## 14. Success criteria

- Todos os 12 atributos listados modelados e carregados via seeder.
- Admin cria produto com 2 eixos de variação, gera matriz 2×3, edita grid, publica; aparece com filtros corretos no storefront.
- Filtro combinado (3 facetas + cor + tamanho + busca textual) retorna resultado correto em p95 < 30 ms (Postgres) / < 3 ms (Redis hit).
- Trocar cor no PDP troca galeria de imagens sem round-trip extra ao servidor.
- Hit rate de Redis em listagens > 80% após 24h de tráfego.
- Rebuild de cache em massa < 60s para 100k produtos.
