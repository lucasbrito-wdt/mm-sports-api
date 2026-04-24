<?php

namespace App\Domains\Integrations\Services;

/**
 * Cotação de frente Correios. Sem credenciais, retorna stub para testes e dev.
 */
class CorreiosService
{
    public function __construct() {}

    /**
     * @param  array<int, array{weight_grams?: int|null, price: string|float}>  $lineWeights  Metadados por item (cubagem simplificada no MVP)
     * @return array{price: float, eta_days: int, service_code: string, raw: array}
     */
    public function quote(string $destinationCep, array $lineWeights, ?string $originCep = null): array
    {
        if (! $this->isConfigured()) {
            return [
                'price' => 0.0,
                'eta_days' => 0,
                'service_code' => 'STUB',
                'raw' => ['stub' => true],
            ];
        }

        // Placeholder: integração real com API dos Correios (contrato) viria aqui.
        return [
            'price' => 0.01,
            'eta_days' => 5,
            'service_code' => (string) (config('services.correios.service_codes.0') ?? '03220'),
            'raw' => [],
        ];
    }

    private function isConfigured(): bool
    {
        $c = config('services.correios', []);

        return ! empty($c['username']) && ! empty($c['posting_card']);
    }
}
