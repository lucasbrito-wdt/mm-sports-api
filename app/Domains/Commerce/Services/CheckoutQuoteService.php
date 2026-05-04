<?php

namespace App\Domains\Commerce\Services;

use App\Domains\Catalog\Enums\PersonalizationOptionType;
use App\Domains\Catalog\Enums\ProductStatus;
use App\Domains\Catalog\Models\Product;
use App\Domains\Catalog\Models\ProductPersonalizationOption;
use App\Domains\Catalog\Models\ProductVariant;
use App\Domains\Commerce\Models\Order;
use App\Domains\Commerce\Models\UserAddress;
use App\Domains\Integrations\Services\CorreiosService;
use App\Domains\Marketing\Enums\PromotionType;
use App\Domains\Marketing\Models\Promotion;
use App\Domains\Shared\Services\BaseService;
use App\Domains\Tracking\Services\AnalyticsService;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

class CheckoutQuoteService extends BaseService
{
    public function __construct(
        private readonly ProductVariant $productVariant,
        private readonly Promotion $promotion,
        private readonly UserAddress $userAddress,
        private readonly Order $order,
        private readonly CorreiosService $correiosService,
        private readonly AnalyticsService $analyticsService,
    ) {
        $this->setModel($this->order);
    }

    /**
     * Escolhe a **promoção com maior desconto em reais** entre as elegíveis (ver escopo em promotion_items).
     */
    public function quoteForUser(string $userId, array $data): array
    {
        $out = $this->computeQuote($userId, $data);

        $cepMasked = $this->maskCep(preg_replace('/\D/', '', (string) $data['destination_postal_code']) ?? '');
        $this->analyticsService->track(
            'checkout_quote_requested',
            $userId,
            array_filter([
                'postal_code' => $cepMasked,
                'line_count' => count($out['lines']),
            ]),
            'api',
            request()
        );

        return $out;
    }

    /**
     * Mesma lógica que {@see quoteForUser} sem evento de analytics (ex.: fluxo interno de criação de pedido).
     */
    public function computeQuote(string $userId, array $data): array
    {
        if (! empty($data['user_address_id'])) {
            $addr = $this->userAddress->newQuery()
                ->where('user_id', $userId)
                ->where('id', $data['user_address_id'])
                ->first();
            if (! $addr) {
                throw new InvalidArgumentException('user_address_id inválido');
            }
        }

        $lines = $this->buildLines($data['items']);
        $subtotal = round(array_sum(array_column($lines, 'line_total')), 2);

        $productIds = array_unique(array_column($lines, 'product_id'));
        $variantIds = array_unique(array_column($lines, 'product_variant_id'));

        $bestDiscount = $this->selectBestDiscount($subtotal, $lines, $productIds, $variantIds);
        $discountTotal = min($bestDiscount, $subtotal);
        $afterDiscount = round($subtotal - $discountTotal, 2);

        $lineWeights = array_map(fn ($l) => [
            'weight_grams' => $l['weight_grams'] ?? 0,
            'price' => $l['line_total'],
        ], $lines);

        $dest = $data['destination_postal_code'];
        $origin = (string) config('services.mm_store.origin_postal_code', '58200230');
        $dest = preg_replace('/\D/', '', $dest) ?? $dest;

        $ship = $this->correiosService->quote($dest, $lineWeights, $origin);
        $shippingTotal = (float) $ship['price'];
        if ($shippingTotal < 0.01) {
            $shippingTotal = 0.01;
        }
        $grand = round($afterDiscount + $shippingTotal, 2);

        return [
            'subtotal' => $subtotal,
            'discount_total' => $discountTotal,
            'shipping_total' => $shippingTotal,
            'grand_total' => $grand,
            'lines' => $lines,
            'shipping' => $ship,
        ];
    }

    /**
     * @param  list<array{product_variant_id: string, quantity: int, personalization?: list<array{option_id: string, value: mixed}>|null}>  $items
     * @return list<array<string, mixed>>
     */
    private function buildLines(array $items): array
    {
        $lines = [];
        foreach ($items as $row) {
            $v = $this->productVariant->newQuery()
                ->with(['product' => fn ($q) => $q->with('personalizationOptions')])
                ->where('id', $row['product_variant_id'])
                ->where('is_active', true)
                ->whereHas('product', fn ($q) => $q->where('status', ProductStatus::Published))
                ->first();
            if (! $v) {
                throw new InvalidArgumentException('Variant inválida ou inativa: '.$row['product_variant_id']);
            }
            $qty = (int) $row['quantity'];
            $p = $v->product;
            $rawPers = $row['personalization'] ?? null;
            if ($rawPers !== null && ! is_array($rawPers)) {
                throw new InvalidArgumentException('O campo items.*.personalization deve ser um array.');
            }
            $resolved = $this->resolvePersonalization($p, $rawPers ?? []);
            $baseUnit = (float) $v->price;
            $unit = round($baseUnit + $resolved['per_unit_add'], 2);
            $lineTotal = round($unit * $qty, 2);
            $label = ($v->attribute_payload['size'] ?? null)
                ? (string) $v->attribute_payload['size']
                : (string) $v->sku;
            $lines[] = [
                'product_id' => (string) $p->id,
                'product_variant_id' => (string) $v->id,
                'sku' => $v->sku,
                'quantity' => $qty,
                'base_unit_price' => $baseUnit,
                'unit_price' => $unit,
                'line_total' => $lineTotal,
                'weight_grams' => $v->weight_grams,
                'product_title' => $p->title,
                'variant_label' => $label,
                'personalization_snapshot' => $resolved['snapshot'],
            ];
        }

        return $lines;
    }

    /**
     * @param  list<array{option_id: string, value: mixed}>  $raw
     * @return array{per_unit_add: float, snapshot: list<array<string, mixed>>}
     */
    private function resolvePersonalization(Product $product, array $raw): array
    {
        /** @var Collection<string, ProductPersonalizationOption> $byId */
        $byId = $product->relationLoaded('personalizationOptions')
            ? $product->personalizationOptions->keyBy('id')
            : $product->personalizationOptions()->get()->keyBy('id');

        if (count($raw) === 0) {
            if (! $product->allows_personalization) {
                return ['per_unit_add' => 0.0, 'snapshot' => []];
            }
            if ($this->countRequiredOptions($byId) > 0) {
                throw new InvalidArgumentException('Personalização obrigatória em falta para o produto «'.$product->title.'».');
            }

            return ['per_unit_add' => 0.0, 'snapshot' => []];
        }

        if (! $product->allows_personalization) {
            throw new InvalidArgumentException('O produto «'.$product->title.'» não aceita personalização.');
        }

        $optionIds = array_column($raw, 'option_id');
        if (count($optionIds) !== count(array_unique($optionIds))) {
            throw new InvalidArgumentException('Opções de personalização duplicadas no mesmo artigo.');
        }

        $perUnitAdd = 0.0;
        $snapshot = [];
        $coveredOptionIds = [];

        foreach ($raw as $i => $row) {
            if (! is_array($row) || empty($row['option_id'])) {
                throw new InvalidArgumentException('Cada posição de personalização deve ter option_id.');
            }
            $oid = (string) $row['option_id'];
            if (! $byId->has($oid)) {
                throw new InvalidArgumentException('Opção de personalização inválida: '.$oid);
            }
            $opt = $byId->get($oid);
            if ((string) $opt->product_id !== (string) $product->id) {
                throw new InvalidArgumentException('A opção não pertence a este produto.');
            }
            if (! array_key_exists('value', $row)) {
                throw new InvalidArgumentException('Cada opção de personalização deve ter value (índice '.$i.').');
            }
            $value = $row['value'];
            $this->assertPersonalizationValue($opt, $value);
            $perUnitAdd += (float) $opt->additional_price;
            $coveredOptionIds[$oid] = true;
            $snapshot[] = [
                'option_id' => (string) $opt->id,
                'type' => $opt->type->value,
                'label' => $opt->label,
                'value' => is_scalar($value) || $value === null ? $value : json_encode($value),
                'additional_price' => (float) $opt->additional_price,
            ];
        }

        foreach ($byId as $id => $opt) {
            if (! $opt->is_required) {
                continue;
            }
            if (empty($coveredOptionIds[$id])) {
                throw new InvalidArgumentException('A opção «'.$opt->label.'» é obrigatória.');
            }
        }

        return ['per_unit_add' => round($perUnitAdd, 2), 'snapshot' => $snapshot];
    }

    private function countRequiredOptions($byId): int
    {
        $n = 0;
        foreach ($byId as $opt) {
            if ($opt->is_required) {
                $n++;
            }
        }

        return $n;
    }

    private function assertPersonalizationValue(ProductPersonalizationOption $opt, mixed $value): void
    {
        match ($opt->type) {
            PersonalizationOptionType::ShortText, PersonalizationOptionType::LongText => (function () use ($opt, $value) {
                if (! is_string($value)) {
                    throw new InvalidArgumentException('A opção «'.$opt->label.'» requer texto.');
                }
                $t = trim($value);
                if ($t === '') {
                    throw new InvalidArgumentException('A opção «'.$opt->label.'» não pode ser vazia.');
                }
                if ($opt->max_length && mb_strlen($t) > (int) $opt->max_length) {
                    throw new InvalidArgumentException('A opção «'.$opt->label.'» excede o tamanho máximo.');
                }
            })(),
            PersonalizationOptionType::Number => (function () use ($opt, $value) {
                if (is_string($value) && is_numeric($value)) {
                    $value = 0 + $value;
                }
                if (! is_int($value) && ! is_float($value)) {
                    if (! (is_string($value) && preg_match('/^-?\d+(\.\d+)?$/', $value))) {
                        throw new InvalidArgumentException('A opção «'.$opt->label.'» requer um número.');
                    }
                }
            })(),
            PersonalizationOptionType::Select => (function () use ($opt, $value) {
                if (! is_string($value) && ! is_int($value) && ! is_float($value)) {
                    throw new InvalidArgumentException('A opção «'.$opt->label.'» requer um valor de lista.');
                }
                $v = (string) $value;
                $list = is_array($opt->options_json) ? $opt->options_json : [];
                if ($list === []) {
                    throw new InvalidArgumentException('A opção «'.$opt->label.'» não tem valores configurados.');
                }
                if (! in_array($v, array_map('strval', $list), true) && ! in_array($v, $list, true)) {
                    throw new InvalidArgumentException('Valor inválido para a opção «'.$opt->label.'».');
                }
            })(),
        };
    }

    private function selectBestDiscount(float $subtotal, array $lines, array $productIds, array $variantIds): float
    {
        $now = Carbon::now();
        $promos = $this->promotion->newQuery()
            ->where('is_active', true)
            ->where('starts_at', '<=', $now)
            ->where('ends_at', '>=', $now)
            ->with('items')
            ->get();

        $best = 0.0;
        foreach ($promos as $promo) {
            if ($promo->min_order_total && $subtotal < (float) $promo->min_order_total) {
                continue;
            }
            if (! $this->promotionAppliesToCart($promo, $productIds, $variantIds)) {
                continue;
            }
            $d = $this->computeDiscount($promo, $subtotal, $lines);
            if ($d > $best) {
                $best = $d;
            }
        }

        return round($best, 2);
    }

    private function promotionAppliesToCart(Promotion $promo, array $productIds, array $variantIds): bool
    {
        $items = $promo->items;
        if ($items->isEmpty()) {
            return false;
        }
        foreach ($items as $pi) {
            if ($pi->product_variant_id && in_array((string) $pi->product_variant_id, $variantIds, true)) {
                return true;
            }
            if ($pi->product_id && in_array((string) $pi->product_id, $productIds, true)) {
                return true;
            }
        }

        return false;
    }

    private function computeDiscount(Promotion $promo, float $subtotal, array $lines): float
    {
        return match ($promo->type) {
            PromotionType::Percent => min($subtotal, $subtotal * ((float) $promo->value / 100.0)),
            PromotionType::FixedAmount => min($subtotal, (float) $promo->value),
            default => 0.0,
        };
    }

    private function maskCep(string $digits8): string
    {
        if (strlen($digits8) < 5) {
            return '*****';
        }

        return substr($digits8, 0, 5).'***';
    }
}
