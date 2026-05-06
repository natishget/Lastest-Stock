<?php

namespace App\Http\Controllers;

use App\Models\Sale;
use App\Services\CostingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CogsController extends Controller
{
    public function __construct(private readonly CostingService $costingService) {}

    public function index(Request $request): Response
    {
        $activeMethod = $this->costingService->resolveMethod();
        $defaultDateRange = $this->defaultDateRange();

        return Inertia::render('cogs/index', [
            'activeMethod' => $activeMethod,
            'methodOptions' => $this->methodOptions(),
            'defaultFilters' => [
                'startDate' => $defaultDateRange['startDate'],
                'endDate' => $defaultDateRange['endDate'],
                'costingMethod' => $activeMethod,
            ],
        ]);
    }

    public function report(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'start_date' => ['required', 'date'],
            'end_date' => ['required', 'date', 'after_or_equal:start_date'],
            'costing_method' => ['nullable', 'string', Rule::in($this->costingService->methods())],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $report = $this->costingService->report(
            startDate: Carbon::parse($validated['start_date']),
            endDate: Carbon::parse($validated['end_date']),
            method: $validated['costing_method'] ?? $this->costingService->resolveMethod(),
            perPage: (int) ($validated['per_page'] ?? 10),
            page: (int) ($validated['page'] ?? 1),
        );

        return response()->json($report);
    }

    /**
     * @return array<int, string>
     */
    private function methodOptions(): array
    {
        return $this->costingService->methods();
    }

    /**
     * @return array{startDate: string, endDate: string}
     */
    private function defaultDateRange(): array
    {
        $latestSaleDate = Sale::query()
            ->where(fn ($query) => $query->where('status', Sale::STATUS_POSTED)->orWhereNull('status'))
            ->max('sale_date');

        if (is_string($latestSaleDate) && $latestSaleDate !== '') {
            $latest = Carbon::parse($latestSaleDate);

            return [
                'startDate' => $latest->copy()->startOfMonth()->toDateString(),
                'endDate' => $latest->copy()->endOfMonth()->toDateString(),
            ];
        }

        return [
            'startDate' => now()->startOfMonth()->toDateString(),
            'endDate' => now()->toDateString(),
        ];
    }
}
