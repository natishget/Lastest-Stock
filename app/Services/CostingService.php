<?php

namespace App\Services;

use App\Models\CogsEntry;
use App\Models\InventoryCostLayer;
use App\Models\InventoryValuation;
use App\Models\SaleItem;
use App\Models\SystemSetting;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\LengthAwarePaginator as Paginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CostingService
{
    public const FIFO = 'FIFO';

    public const LIFO = 'LIFO';

    public const WEIGHTED_AVERAGE = 'WEIGHTED_AVERAGE';

    /**
     * @return array<int, string>
     */
    public function methods(): array
    {
        return [
            self::FIFO,
            self::LIFO,
            self::WEIGHTED_AVERAGE,
        ];
    }

    public function resolveMethod(?string $method = null): string
    {
        $normalizedMethod = $this->normalizeMethod($method);

        if ($normalizedMethod !== null) {
            return $normalizedMethod;
        }

        $activeMethod = SystemSetting::query()
            ->with('activeCostingMethod')
            ->first()?->activeCostingMethod?->name;

        return $this->normalizeMethod($activeMethod) ?? self::FIFO;
    }

    public function recordPurchase(string $variantId, string $warehouseId, float $quantity, float $unitCost): void
    {
        InventoryCostLayer::query()->create([
            'variant_id' => $variantId,
            'warehouse_id' => $warehouseId,
            'remaining_qty' => $quantity,
            'unit_cost' => $unitCost,
            'source_transaction_id' => null,
        ]);

        $this->adjustInventoryValuation($variantId, $warehouseId, $quantity, round($quantity * $unitCost, 2));
    }

    public function recordSaleItemCogs(SaleItem $saleItem, string $warehouseId, ?string $method = null): float
    {
        $costingMethod = $this->resolveMethod($method);

        return match ($costingMethod) {
            self::LIFO => $this->consumeLayersAndCreateEntries($saleItem, $warehouseId, $costingMethod, 'desc'),
            self::WEIGHTED_AVERAGE => $this->consumeWeightedAverage($saleItem, $warehouseId),
            default => $this->consumeLayersAndCreateEntries($saleItem, $warehouseId, $costingMethod, 'asc'),
        };
    }

    /**
     * @return LengthAwarePaginator<int, array<string, mixed>>
     */
    public function report(Carbon $startDate, Carbon $endDate, string $method, int $perPage = 10, int $page = 1): Paginator
    {
        $costingMethod = $this->resolveMethod($method);

        $saleSummary = DB::table('sale_items as si')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->whereBetween('s.sale_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->selectRaw('si.variant_id, SUM(si.quantity) as quantity_sold, SUM(si.total_price) as revenue')
            ->groupBy('si.variant_id');

        $cogsSummary = DB::table('cogs_entries as ce')
            ->join('sale_items as si', 'si.id', '=', 'ce.sale_item_id')
            ->join('sales as s', 's.id', '=', 'si.sale_id')
            ->whereBetween('s.sale_date', [$startDate->toDateString(), $endDate->toDateString()])
            ->where('ce.costing_method', $costingMethod)
            ->selectRaw('ce.variant_id, SUM(ce.total_cost) as cogs')
            ->groupBy('ce.variant_id');

        return DB::query()
            ->fromSub($saleSummary, 'sale_summary')
            ->leftJoinSub($cogsSummary, 'cogs_summary', 'sale_summary.variant_id', '=', 'cogs_summary.variant_id')
            ->join('product_variants as pv', 'pv.id', '=', 'sale_summary.variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->selectRaw('sale_summary.variant_id, p.name as product_name, pv.color, pv.origin, sale_summary.quantity_sold, sale_summary.revenue, COALESCE(cogs_summary.cogs, 0) as cogs, sale_summary.revenue - COALESCE(cogs_summary.cogs, 0) as gross_profit, CASE WHEN sale_summary.revenue = 0 THEN 0 ELSE ROUND(((sale_summary.revenue - COALESCE(cogs_summary.cogs, 0)) / sale_summary.revenue) * 100, 2) END as profit_margin')
            ->orderBy('p.name')
            ->orderBy('pv.color')
            ->orderBy('pv.origin')
            ->paginate($perPage, ['*'], 'page', $page);
    }

    private function consumeLayersAndCreateEntries(SaleItem $saleItem, string $warehouseId, string $costingMethod, string $direction): float
    {
        $layers = InventoryCostLayer::query()
            ->where('variant_id', $saleItem->variant_id)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_qty', '>', 0)
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction)
            ->lockForUpdate()
            ->get();

        $availableQuantity = (float) $layers->sum('remaining_qty');

        if ($availableQuantity < (float) $saleItem->quantity) {
            throw ValidationException::withMessages([
                'items' => 'Not enough available stock in the selected warehouse for one of the selected variants.',
            ]);
        }

        $remainingQuantity = (float) $saleItem->quantity;
        $totalCost = 0.0;

        foreach ($layers as $layer) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $consumedQuantity = min((float) $layer->remaining_qty, $remainingQuantity);
            $lineCost = round($consumedQuantity * (float) $layer->unit_cost, 2);

            CogsEntry::query()->create([
                'sale_item_id' => $saleItem->id,
                'variant_id' => $saleItem->variant_id,
                'quantity' => $consumedQuantity,
                'unit_cost' => $layer->unit_cost,
                'total_cost' => $lineCost,
                'costing_method' => $costingMethod,
                'source_layer_id' => $layer->id,
            ]);

            $layer->update([
                'remaining_qty' => (float) $layer->remaining_qty - $consumedQuantity,
            ]);

            $totalCost += $lineCost;
            $remainingQuantity -= $consumedQuantity;
        }

        return $totalCost;
    }

    private function consumeWeightedAverage(SaleItem $saleItem, string $warehouseId): float
    {
        $valuation = InventoryValuation::query()
            ->where('variant_id', $saleItem->variant_id)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $availableQuantity = (float) ($valuation?->total_quantity ?? 0);

        if ($availableQuantity < (float) $saleItem->quantity) {
            throw ValidationException::withMessages([
                'items' => 'Not enough available stock in the selected warehouse for one of the selected variants.',
            ]);
        }

        $avgUnitCost = (float) ($valuation?->avg_unit_cost ?? 0);
        $totalCost = round((float) $saleItem->quantity * $avgUnitCost, 2);

        $this->consumeLayersAndUpdateStockOnly($saleItem, $warehouseId);

        CogsEntry::query()->create([
            'sale_item_id' => $saleItem->id,
            'variant_id' => $saleItem->variant_id,
            'quantity' => $saleItem->quantity,
            'unit_cost' => $avgUnitCost,
            'total_cost' => $totalCost,
            'costing_method' => self::WEIGHTED_AVERAGE,
            'source_layer_id' => null,
        ]);

        $this->adjustInventoryValuation($saleItem->variant_id, $warehouseId, -(float) $saleItem->quantity, -$totalCost);

        return $totalCost;
    }

    private function consumeLayersAndUpdateStockOnly(SaleItem $saleItem, string $warehouseId): void
    {
        $layers = InventoryCostLayer::query()
            ->where('variant_id', $saleItem->variant_id)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_qty', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        $remainingQuantity = (float) $saleItem->quantity;

        foreach ($layers as $layer) {
            if ($remainingQuantity <= 0) {
                break;
            }

            $consumedQuantity = min((float) $layer->remaining_qty, $remainingQuantity);

            $layer->update([
                'remaining_qty' => (float) $layer->remaining_qty - $consumedQuantity,
            ]);

            $remainingQuantity -= $consumedQuantity;
        }
    }

    private function adjustInventoryValuation(string $variantId, string $warehouseId, float $quantityDelta, float $costDelta): void
    {
        $valuation = InventoryValuation::query()
            ->where('variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->lockForUpdate()
            ->first();

        $currentQuantity = (float) ($valuation?->total_quantity ?? 0);
        $currentCost = (float) ($valuation?->total_cost ?? 0);

        $updatedQuantity = round($currentQuantity + $quantityDelta, 4);
        $updatedCost = round($currentCost + $costDelta, 4);

        if ($updatedQuantity < 0 || $updatedCost < 0) {
            throw ValidationException::withMessages([
                'items' => 'Inventory valuation would become negative for the selected warehouse.',
            ]);
        }

        DB::table('inventory_valuation')->updateOrInsert(
            [
                'variant_id' => $variantId,
                'warehouse_id' => $warehouseId,
            ],
            [
                'total_quantity' => $updatedQuantity,
                'total_cost' => $updatedCost,
                'avg_unit_cost' => $updatedQuantity > 0 ? round($updatedCost / $updatedQuantity, 4) : 0,
            ],
        );
    }

    private function normalizeMethod(?string $method): ?string
    {
        $normalizedMethod = strtoupper(trim((string) $method));

        if ($normalizedMethod === '') {
            return null;
        }

        return in_array($normalizedMethod, $this->methods(), true) ? $normalizedMethod : null;
    }
}
