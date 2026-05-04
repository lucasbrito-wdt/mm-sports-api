<?php

namespace App\Domains\Sports\Controllers\Admin;

use App\Domains\Sports\Models\Team;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TeamController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $perPage = (int) $request->integer('per_page', 30);
        $perPage = max(1, min($perPage, 100));

        $query = Team::query()
            ->select([
                'id',
                'external_id',
                'name',
                'short_name',
                'symbolic_name',
                'sport_id',
                'country_id',
                'logo_url',
                'popularity_rank',
            ])
            ->when($request->filled('sport_id'), fn ($q) => $q->where('sport_id', (int) $request->input('sport_id')))
            ->when($request->filled('country_id'), fn ($q) => $q->where('country_id', (int) $request->input('country_id')))
            ->when($request->filled('search'), function ($q) use ($request) {
                $term = trim((string) $request->input('search'));
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'ilike', "%{$term}%")
                        ->orWhere('short_name', 'ilike', "%{$term}%")
                        ->orWhere('symbolic_name', 'ilike', "%{$term}%");
                });
            })
            ->when($request->filled('ids'), function ($q) use ($request) {
                $ids = collect(explode(',', (string) $request->input('ids')))
                    ->map(fn ($v) => (int) trim($v))
                    ->filter()
                    ->values()
                    ->all();

                if ($ids !== []) {
                    $q->whereIn('external_id', $ids);
                }
            })
            ->orderByRaw('popularity_rank IS NULL, popularity_rank DESC')
            ->orderBy('name');

        $paginator = $query->paginate($perPage)->withQueryString();

        return response()->json([
            'data' => $paginator->items(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'last_page' => $paginator->lastPage(),
            ],
        ]);
    }
}
