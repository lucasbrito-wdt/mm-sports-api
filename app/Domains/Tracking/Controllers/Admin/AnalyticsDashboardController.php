<?php

namespace App\Domains\Tracking\Controllers\Admin;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;

class AnalyticsDashboardController extends Controller
{
    public function kpis(Request $request): JsonResponse
    {
        $days = min(max((int) $request->query('days', 30), 1), 365);
        $double = $days * 2;

        $rows = DB::select("
            WITH periodos AS (
                SELECT
                    CASE
                        WHEN event_timestamp >= NOW() - INTERVAL '{$days} days' THEN 'atual'
                        WHEN event_timestamp >= NOW() - INTERVAL '{$double} days' THEN 'anterior'
                    END AS periodo,
                    event_name,
                    session_id,
                    order_id,
                    revenue_cents
                FROM events
                WHERE event_timestamp >= NOW() - INTERVAL '{$double} days'
            )
            SELECT
                periodo,
                COUNT(DISTINCT session_id) AS sessoes,
                COUNT(DISTINCT CASE WHEN event_name = 'purchase' THEN order_id END) AS pedidos,
                COALESCE(SUM(CASE WHEN event_name = 'purchase' THEN revenue_cents END), 0) / 100.0 AS receita,
                ROUND(
                    COUNT(DISTINCT CASE WHEN event_name = 'purchase' THEN order_id END)::numeric
                    / NULLIF(COUNT(DISTINCT session_id), 0) * 100,
                    2
                ) AS taxa_conversao_pct,
                ROUND(
                    (COALESCE(SUM(CASE WHEN event_name = 'purchase' THEN revenue_cents END), 0)
                     / NULLIF(COUNT(DISTINCT CASE WHEN event_name = 'purchase' THEN order_id END), 0))
                    / 100.0,
                    2
                ) AS ticket_medio
            FROM periodos
            WHERE periodo IS NOT NULL
            GROUP BY periodo
        ");

        $result = ['atual' => null, 'anterior' => null];
        foreach ($rows as $row) {
            $result[$row->periodo] = (array) $row;
        }

        return response()->json($result);
    }

    public function funnel(Request $request): JsonResponse
    {
        $days = min(max((int) $request->query('days', 30), 1), 365);

        $row = DB::selectOne("
            WITH funil AS (
                SELECT
                    session_id,
                    MAX(CASE WHEN event_name = 'page_view'      THEN 1 ELSE 0 END) AS visitou,
                    MAX(CASE WHEN event_name = 'view_item'      THEN 1 ELSE 0 END) AS viu_produto,
                    MAX(CASE WHEN event_name = 'add_to_cart'    THEN 1 ELSE 0 END) AS carrinho,
                    MAX(CASE WHEN event_name = 'begin_checkout' THEN 1 ELSE 0 END) AS checkout,
                    MAX(CASE WHEN event_name = 'purchase'       THEN 1 ELSE 0 END) AS comprou
                FROM events
                WHERE event_timestamp >= NOW() - INTERVAL '{$days} days'
                GROUP BY session_id
            )
            SELECT
                SUM(visitou)      AS visitas,
                SUM(viu_produto)  AS viu_produto,
                SUM(carrinho)     AS carrinho,
                SUM(checkout)     AS checkout,
                SUM(comprou)      AS pedido,
                ROUND(SUM(viu_produto)::numeric / NULLIF(SUM(visitou), 0)     * 100, 1) AS pct_v_produto,
                ROUND(SUM(carrinho)::numeric    / NULLIF(SUM(viu_produto), 0) * 100, 1) AS pct_produto_cart,
                ROUND(SUM(checkout)::numeric    / NULLIF(SUM(carrinho), 0)    * 100, 1) AS pct_cart_checkout,
                ROUND(SUM(comprou)::numeric     / NULLIF(SUM(checkout), 0)    * 100, 1) AS pct_checkout_pedido,
                ROUND(SUM(comprou)::numeric     / NULLIF(SUM(visitou), 0)     * 100, 2) AS conversao_total
            FROM funil
        ");

        return response()->json($row ?? (object) []);
    }

    public function revenueDaily(Request $request): JsonResponse
    {
        $days = min(max((int) $request->query('days', 30), 1), 365);

        $rows = DB::select("
            SELECT
                DATE(event_timestamp) AS dia,
                COUNT(DISTINCT order_id) AS pedidos,
                SUM(revenue_cents) / 100.0 AS receita
            FROM events
            WHERE event_name = 'purchase'
              AND event_timestamp >= NOW() - INTERVAL '{$days} days'
            GROUP BY DATE(event_timestamp)
            ORDER BY dia
        ");

        return response()->json($rows);
    }

    public function topProducts(Request $request): JsonResponse
    {
        $days  = min(max((int) $request->query('days', 30), 1), 365);
        $limit = min((int) $request->query('limit', 20), 100);

        $rows = DB::select("
            SELECT
                e.product_id,
                COUNT(DISTINCT CASE WHEN e.event_name = 'view_item'   THEN e.session_id END) AS visualizacoes,
                COUNT(DISTINCT CASE WHEN e.event_name = 'add_to_cart' THEN e.session_id END) AS add_carrinho,
                COUNT(DISTINCT CASE WHEN e.event_name = 'purchase'    THEN e.order_id   END) AS vendas,
                COALESCE(SUM(CASE WHEN e.event_name = 'purchase' THEN e.revenue_cents END), 0) / 100.0 AS receita,
                ROUND(
                    COUNT(DISTINCT CASE WHEN e.event_name = 'purchase' THEN e.order_id END)::numeric
                    / NULLIF(COUNT(DISTINCT CASE WHEN e.event_name = 'view_item' THEN e.session_id END), 0) * 100,
                    2
                ) AS conversao_pct
            FROM events e
            WHERE e.event_timestamp >= NOW() - INTERVAL '{$days} days'
              AND e.event_name IN ('view_item', 'add_to_cart', 'purchase')
              AND e.product_id IS NOT NULL
            GROUP BY e.product_id
            ORDER BY receita DESC NULLS LAST
            LIMIT {$limit}
        ");

        return response()->json($rows);
    }

    public function acquisition(Request $request): JsonResponse
    {
        $days = min(max((int) $request->query('days', 30), 1), 365);

        $rows = DB::select("
            SELECT
                COALESCE(utm_source, '(direto)') AS source,
                COALESCE(utm_medium, '(none)')   AS medium,
                COUNT(DISTINCT session_id) AS sessoes,
                COUNT(DISTINCT CASE WHEN event_name = 'purchase' THEN order_id END) AS pedidos,
                COALESCE(SUM(CASE WHEN event_name = 'purchase' THEN revenue_cents END), 0) / 100.0 AS receita,
                ROUND(
                    COUNT(DISTINCT CASE WHEN event_name = 'purchase' THEN order_id END)::numeric
                    / NULLIF(COUNT(DISTINCT session_id), 0) * 100,
                    2
                ) AS conversao_pct
            FROM events
            WHERE event_timestamp >= NOW() - INTERVAL '{$days} days'
            GROUP BY utm_source, utm_medium
            ORDER BY receita DESC NULLS LAST
            LIMIT 20
        ");

        return response()->json($rows);
    }
}
