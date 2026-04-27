<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $currentFiscalYear = $this->currentFiscalYear();

        return Inertia::render('dashboard', [
            'defaultFiscalYear' => $currentFiscalYear,
            'fiscalYears' => $this->fiscalYearOptions($currentFiscalYear),
        ]);
    }

    public function financials(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
        ]);

        $fiscalYear = $this->normalizeFiscalYear($validated['fiscal_year'] ?? null);

        return response()->json($this->financialPayload($fiscalYear));
    }

    public function topProducts(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'fiscal_year' => ['nullable', 'integer', 'min:2000', 'max:2100'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:15'],
        ]);

        $fiscalYear = $this->normalizeFiscalYear($validated['fiscal_year'] ?? null);
        $limit = (int) ($validated['limit'] ?? 10);

        return response()->json($this->topProductsPayload($fiscalYear, $limit));
    }

    /**
     * @return array{fiscal_year:int,start_date:string,end_date:string,months:array<int,array<string,mixed>>,totals:array<string,float>}
     */
    private function financialPayload(int $fiscalYear): array
    {
        $cacheKey = "dashboard.financials.{$fiscalYear}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($fiscalYear): array {
            [$startDate, $endDate] = $this->fiscalYearRange($fiscalYear);

            $revenueByDate = DB::table('sales as s')
                ->join('sale_items as si', 'si.sale_id', '=', 's.id')
                ->whereBetween('s.sale_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->where(fn ($query) => $query->where('s.status', Sale::STATUS_POSTED)->orWhereNull('s.status'))
                ->selectRaw('s.sale_date as sale_date, COALESCE(SUM(si.total_price), 0) as revenue')
                ->groupBy('s.sale_date')
                ->pluck('revenue', 'sale_date');

            $cogsByDate = DB::table('sales as s')
                ->join('sale_items as si', 'si.sale_id', '=', 's.id')
                ->join('cogs_entries as ce', 'ce.sale_item_id', '=', 'si.id')
                ->whereBetween('s.sale_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->where(fn ($query) => $query->where('s.status', Sale::STATUS_POSTED)->orWhereNull('s.status'))
                ->selectRaw('s.sale_date as sale_date, COALESCE(SUM(ce.total_cost), 0) as cogs')
                ->groupBy('s.sale_date')
                ->pluck('cogs', 'sale_date');

            $months = [];
            $monthCursor = $startDate->copy()->startOfMonth();
            $monthEnd = $endDate->copy()->startOfMonth();

            while ($monthCursor->lessThanOrEqualTo($monthEnd)) {
                $monthKey = $monthCursor->format('Y-m');
                $monthRevenue = 0.0;
                $monthCogs = 0.0;

                foreach (array_keys($revenueByDate->all()) as $saleDate) {
                    if (Carbon::parse($saleDate)->format('Y-m') === $monthKey) {
                        $monthRevenue += (float) $revenueByDate[$saleDate];
                    }
                }

                foreach (array_keys($cogsByDate->all()) as $saleDate) {
                    if (Carbon::parse($saleDate)->format('Y-m') === $monthKey) {
                        $monthCogs += (float) $cogsByDate[$saleDate];
                    }
                }

                $months[] = [
                    'month' => $monthKey,
                    'label' => $monthCursor->format('M Y'),
                    'revenue' => round($monthRevenue, 2),
                    'cogs' => round($monthCogs, 2),
                    'gross_profit' => round($monthRevenue - $monthCogs, 2),
                ];

                $monthCursor->addMonthNoOverflow();
            }

            $totals = [
                'revenue' => round(array_sum(array_column($months, 'revenue')), 2),
                'cogs' => round(array_sum(array_column($months, 'cogs')), 2),
                'gross_profit' => round(array_sum(array_column($months, 'gross_profit')), 2),
            ];

            return [
                'fiscal_year' => $fiscalYear,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'months' => $months,
                'totals' => $totals,
            ];
        });
    }

    /**
     * @return array{fiscal_year:int,start_date:string,end_date:string,limit:int,data:array<int,array<string,mixed>>}
     */
    private function topProductsPayload(int $fiscalYear, int $limit): array
    {
        $cacheKey = "dashboard.top-products.{$fiscalYear}.{$limit}";

        return Cache::remember($cacheKey, now()->addMinutes(5), function () use ($fiscalYear, $limit): array {
            [$startDate, $endDate] = $this->fiscalYearRange($fiscalYear);

            $rows = DB::table('sale_items as si')
                ->join('sales as s', 's.id', '=', 'si.sale_id')
                ->join('product_variants as pv', 'pv.id', '=', 'si.variant_id')
                ->join('products as p', 'p.id', '=', 'pv.product_id')
                ->whereBetween('s.sale_date', [$startDate->toDateString(), $endDate->toDateString()])
                ->where(fn ($query) => $query->where('s.status', Sale::STATUS_POSTED)->orWhereNull('s.status'))
                ->selectRaw('pv.id as variant_id, p.name as product_name, pv.color, pv.origin, COALESCE(SUM(si.quantity), 0) as total_quantity, COALESCE(SUM(si.total_price), 0) as revenue')
                ->groupBy('pv.id', 'p.name', 'pv.color', 'pv.origin')
                ->orderByDesc('total_quantity')
                ->limit($limit)
                ->get()
                ->map(fn ($row): array => [
                    'variant_id' => $row->variant_id,
                    'product_name' => $row->product_name,
                    'color' => $row->color,
                    'origin' => $row->origin,
                    'label' => trim(implode(' - ', array_filter([
                        $row->product_name,
                        $this->displayText($row->color),
                        $this->displayText($row->origin),
                    ]))),
                    'total_quantity' => round((float) $row->total_quantity, 4),
                    'revenue' => round((float) $row->revenue, 2),
                ])
                ->all();

            return [
                'fiscal_year' => $fiscalYear,
                'start_date' => $startDate->toDateString(),
                'end_date' => $endDate->toDateString(),
                'limit' => $limit,
                'data' => $rows,
            ];
        });
    }

    /**
     * @return array{0:Carbon,1:Carbon}
     */
    private function fiscalYearRange(int $fiscalYear): array
    {
        return [
            Carbon::create($fiscalYear, 7, 1)->startOfDay(),
            Carbon::create($fiscalYear + 1, 6, 30)->endOfDay(),
        ];
    }

    private function currentFiscalYear(): int
    {
        $today = now();

        return $today->month >= 7 ? $today->year : $today->year - 1;
    }

    private function normalizeFiscalYear(?int $fiscalYear): int
    {
        return $fiscalYear ?? $this->currentFiscalYear();
    }

    /**
     * @return array<int, int>
     */
    private function fiscalYearOptions(int $currentFiscalYear): array
    {
        $options = [];

        for ($year = $currentFiscalYear - 3; $year <= $currentFiscalYear + 1; $year++) {
            $options[] = $year;
        }

        return $options;
    }

    private function displayText(mixed $value): ?string
    {
        $text = trim((string) $value);

        if ($text === '') {
            return null;
        }

        return Str::of($text)->lower()->title()->toString();
    }
}
