<?php

namespace App\Domains\Integrations\Services;

use Correios\DTOs\Preco\CotacaoPrecoRequestDTO;
use Correios\Facades\Correios;
use Throwable;

/**
 * Fachada interna sobre o pacote lucasbrito-wdt/correios.
 * Retorna opções de frete (SEDEX + PAC) com preço e prazo via Correios CWS.
 */
class CorreiosService
{
    /** @var array<string, string> Código → nome legível */
    private const SERVICE_NAMES = [
        '03220' => 'SEDEX',
        '03298' => 'PAC',
    ];

    /** Dimensões padrão de uma camiseta embalada (em cm) */
    private const DEFAULT_COMPRIMENTO = 30;

    private const DEFAULT_LARGURA = 20;

    private const DEFAULT_ALTURA = 5;

    /** Peso mínimo aceito pela API (gramas) */
    private const PESO_MINIMO = 100;

    /**
     * Retorna opções de frete disponíveis (SEDEX e/ou PAC).
     *
     * @param  array<int, array{weight_grams?: int|null, price: string|float}>  $lineWeights
     * @return array{
     *   price: float,
     *   eta_days: int,
     *   service_code: string,
     *   service_name: string,
     *   options: list<array{service_code: string, service_name: string, price: float, eta_days: int}>
     * }
     */
    public function quote(string $destinationCep, array $lineWeights, ?string $originCep = null): array
    {
        $origin = preg_replace('/\D/', '', $originCep ?? config('services.mm_store.origin_postal_code', '58200230'));
        $dest = preg_replace('/\D/', '', $destinationCep);
        $peso = max(self::PESO_MINIMO, $this->totalWeight($lineWeights));

        if (! $this->isConfigured()) {
            return $this->stub($origin, $dest);
        }

        $serviceCodes = array_keys(self::SERVICE_NAMES);
        $dtPostagem = now()->toDateString();

        // ── Preço em lote ────────────────────────────────────────────────────
        $precoRequests = array_map(
            fn (string $co) => new CotacaoPrecoRequestDTO(
                coProduto: $co,
                cepOrigem: $origin,
                cepDestino: $dest,
                psObjeto: $peso,
                tpObjeto: 2,
                comprimento: self::DEFAULT_COMPRIMENTO,
                largura: self::DEFAULT_LARGURA,
                altura: self::DEFAULT_ALTURA,
            ),
            $serviceCodes
        );

        try {
            $precoResps = Correios::preco()->cotarLote($precoRequests);
        } catch (Throwable) {
            return $this->stub($origin, $dest);
        }

        // ── Prazo em lote ────────────────────────────────────────────────────
        $prazoItems = array_map(
            fn (string $co) => [
                'coProduto' => $co,
                'cepOrigem' => $origin,
                'cepDestino' => $dest,
                'dataPostagem' => $dtPostagem,
            ],
            $serviceCodes
        );

        try {
            $prazoResps = Correios::prazo()->calcularLote($prazoItems);
        } catch (Throwable) {
            $prazoResps = [];
        }

        // ── Monta opções ─────────────────────────────────────────────────────
        $options = [];
        foreach ($precoResps as $i => $preco) {
            if ($preco->temErro()) {
                continue;
            }
            $co = $serviceCodes[$i];
            $etaDays = isset($prazoResps[$i]) ? max(1, $prazoResps[$i]->prazoEntrega) : 7;

            $options[] = [
                'service_code' => $co,
                'service_name' => self::SERVICE_NAMES[$co] ?? $co,
                'price' => round($preco->pcFinal, 2),
                'eta_days' => $etaDays,
            ];
        }

        if (empty($options)) {
            return $this->stub($origin, $dest);
        }

        // Ordena: mais barato primeiro
        usort($options, fn ($a, $b) => $a['price'] <=> $b['price']);
        $first = $options[0];

        return [
            'price' => $first['price'],
            'eta_days' => $first['eta_days'],
            'service_code' => $first['service_code'],
            'service_name' => $first['service_name'],
            'options' => $options,
        ];
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Soma o peso de todos os itens. */
    private function totalWeight(array $lineWeights): int
    {
        $total = 0;
        foreach ($lineWeights as $lw) {
            $total += (int) ($lw['weight_grams'] ?? 300);
        }

        return $total;
    }

    private function isConfigured(): bool
    {
        $creds = config('correios.credenciais', []);

        return ! empty($creds['usuario']) && ! empty($creds['cartao_postagem']);
    }

    /**
     * Stub retornado em ambiente de dev sem credenciais.
     *
     * @return array{price: float, eta_days: int, service_code: string, service_name: string, options: list<array{service_code: string, service_name: string, price: float, eta_days: int}>}
     */
    private function stub(string $origin, string $dest): array
    {
        $options = [
            ['service_code' => '03220', 'service_name' => 'SEDEX', 'price' => 24.90, 'eta_days' => 3],
            ['service_code' => '03298', 'service_name' => 'PAC',   'price' => 9.90, 'eta_days' => 8],
        ];

        return [
            'price' => 9.90,
            'eta_days' => 8,
            'service_code' => '03298',
            'service_name' => 'PAC',
            'options' => $options,
        ];
    }
}
