<?php

namespace App\Domains\Sports\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Http\Client\Pool;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

class TeamSeeder extends Seeder
{
    private const SOURCE_FILE = 'data/competitions_countries_sports.json';

    private const API_BASE = 'https://webws.365scores.com/web/games/current/';

    private const LOGO_URL_TEMPLATE = 'https://imagecache.365scores.com/image/upload/f_png,w_34,h_34,c_limit,q_auto:eco,dpr_2,d_Competitors:default1.png/v9/Competitors/%d';

    private const POOL_SIZE = 10;

    private const UPSERT_BATCH = 500;

    public function run(): void
    {
        $path = base_path(self::SOURCE_FILE);

        if (! is_file($path)) {
            $this->command?->error("Arquivo não encontrado: {$path}");

            return;
        }

        $data = json_decode((string) file_get_contents($path), true);
        $competitions = $data['competitions'] ?? [];

        if ($competitions === []) {
            $this->command?->warn('Nenhuma competition encontrada no JSON.');

            return;
        }

        $total = count($competitions);
        $this->command?->info("Processando {$total} competitions em chunks de ".self::POOL_SIZE.'...');

        $teams = [];
        $processed = 0;

        foreach (array_chunk($competitions, self::POOL_SIZE) as $chunk) {
            $responses = $this->fetchChunk($chunk);

            foreach ($responses as $index => $response) {
                $competition = $chunk[$index];
                $this->extractTeams($response, $competition, $teams);
            }

            $processed += count($chunk);
            $this->command?->info("  ... {$processed}/{$total} (times únicos: ".count($teams).')');
        }

        if ($teams === []) {
            $this->command?->warn('Nenhum time extraído.');

            return;
        }

        $this->upsertTeams($teams);

        $this->command?->info('Total de times salvos: '.count($teams));
    }

    /**
     * @param  array<int, array<string, mixed>>  $chunk
     * @return array<int, array<string, mixed>|null>
     */
    private function fetchChunk(array $chunk): array
    {
        try {
            $rawResponses = Http::pool(function (Pool $pool) use ($chunk) {
                return array_map(
                    fn (array $competition) => $pool
                        ->timeout(20)
                        ->acceptJson()
                        ->get(self::API_BASE, [
                            'appTypeId' => 5,
                            'langId' => 31,
                            'userCountryId' => $competition['countryId'] ?? 0,
                            'competitions' => $competition['id'],
                        ]),
                    $chunk,
                );
            });
        } catch (Throwable $e) {
            $this->command?->warn('Erro no pool HTTP: '.$e->getMessage());

            return array_fill(0, count($chunk), null);
        }

        $parsed = [];
        foreach ($rawResponses as $response) {
            try {
                $parsed[] = $response && $response->successful() ? $response->json() : null;
            } catch (Throwable) {
                $parsed[] = null;
            }
        }

        return $parsed;
    }

    /**
     * @param  array<string, mixed>|null  $payload
     * @param  array<string, mixed>  $competition
     * @param  array<int, array<string, mixed>>  $teams
     */
    private function extractTeams(?array $payload, array $competition, array &$teams): void
    {
        $games = $payload['games'] ?? [];

        if (! is_array($games) || $games === []) {
            return;
        }

        $countryId = (int) ($competition['countryId'] ?? 0);
        $sportId = (int) ($competition['sportId'] ?? 0);

        foreach ($games as $game) {
            foreach (['homeCompetitor', 'awayCompetitor'] as $key) {
                $competitor = $game[$key] ?? null;

                if (! is_array($competitor) || empty($competitor['id'])) {
                    continue;
                }

                $externalId = (int) $competitor['id'];

                if (isset($teams[$externalId])) {
                    continue;
                }

                $teams[$externalId] = [
                    'id' => (string) Str::ulid(),
                    'external_id' => $externalId,
                    'name' => (string) ($competitor['name'] ?? 'Unknown'),
                    'short_name' => $competitor['shortName'] ?? null,
                    'name_for_url' => $competitor['nameForURL'] ?? null,
                    'symbolic_name' => $competitor['symbolicName'] ?? null,
                    'sport_id' => $competitor['sportId'] ?? $sportId ?: null,
                    'country_id' => $competitor['countryId'] ?? $countryId ?: null,
                    'popularity_rank' => $competitor['popularityRank'] ?? null,
                    'color' => $competitor['color'] ?? null,
                    'logo_url' => sprintf(self::LOGO_URL_TEMPLATE, $externalId),
                    'image_version' => $competitor['imageVersion'] ?? null,
                    'created_at' => now(),
                    'updated_at' => now(),
                ];
            }
        }
    }

    /**
     * @param  array<int, array<string, mixed>>  $teams
     */
    private function upsertTeams(array $teams): void
    {
        $columns = [
            'name',
            'short_name',
            'name_for_url',
            'symbolic_name',
            'sport_id',
            'country_id',
            'popularity_rank',
            'color',
            'logo_url',
            'image_version',
            'updated_at',
        ];

        foreach (array_chunk(array_values($teams), self::UPSERT_BATCH) as $batch) {
            DB::table('teams')->upsert($batch, ['external_id'], $columns);
        }
    }
}
