<?php

namespace App\Services;

use App\Models\CogsEntry;
use App\Models\InventoryCostLayer;
use App\Models\InventoryTransaction;
use App\Models\Purchase;
use App\Models\Sale;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CorrectionService
{
    public function __construct(private readonly CostingService $costingService) {}

    public function voidSale(string $saleId, ?string $userId, ?string $reason = null, ?string $voidDate = null): Sale
    {
        return DB::transaction(function () use ($saleId, $userId, $reason, $voidDate): Sale {
            $sale = Sale::query()->with(['items.cogsEntries'])->lockForUpdate()->findOrFail($saleId);

            if ($sale->status === Sale::STATUS_VOIDED) {
                throw ValidationException::withMessages([
                    'sale' => 'Sale is already voided.',
                ]);
            }

            $saleTransactions = InventoryTransaction::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->where('transaction_type', 'SALE')
                ->lockForUpdate()
                ->get();

            if ($saleTransactions->isEmpty()) {
                throw ValidationException::withMessages([
                    'sale' => 'No sale inventory transactions were found for this sale.',
                ]);
            }

            $warehouseByVariant = $saleTransactions
                ->groupBy('variant_id')
                ->map(fn (Collection $transactions): ?string => $transactions->first()?->warehouse_id);

            foreach ($saleTransactions as $transaction) {
                InventoryTransaction::query()->create([
                    'variant_id' => $transaction->variant_id,
                    'warehouse_id' => $transaction->warehouse_id,
                    'transaction_type' => 'SALE_RETURN',
                    'quantity' => abs((float) $transaction->quantity),
                    'unit_cost' => $transaction->unit_cost,
                    'reference_type' => Sale::REFERENCE_VOID,
                    'reference_id' => $sale->id,
                    'transaction_date' => $voidDate ?? now()->toDateString(),
                    'created_by' => $userId,
                ]);
            }

            foreach ($sale->items as $saleItem) {
                $warehouseId = $warehouseByVariant->get($saleItem->variant_id);

                if (! is_string($warehouseId) || $warehouseId === '') {
                    continue;
                }

                $this->reverseCogsEntries(
                    entries: $saleItem->cogsEntries,
                    warehouseId: $warehouseId,
                    userId: $userId,
                    reverseDate: $voidDate,
                );
            }

            $sale->update([
                'status' => Sale::STATUS_VOIDED,
                'reference_type' => Sale::REFERENCE_VOID,
                'reference_id' => $sale->id,
                'notes' => $this->appendNote((string) ($sale->notes ?? ''), $reason),
            ]);

            return $sale->fresh(['items.cogsEntries']) ?? $sale;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function returnSale(string $saleId, string $warehouseId, array $items, ?string $userId, ?string $notes = null, ?string $returnDate = null): Sale
    {
        return DB::transaction(function () use ($saleId, $warehouseId, $items, $userId, $notes, $returnDate): Sale {
            $sale = Sale::query()->with(['items.cogsEntries'])->lockForUpdate()->findOrFail($saleId);

            if ($sale->status !== Sale::STATUS_POSTED) {
                throw ValidationException::withMessages([
                    'sale' => 'Only posted sales can be returned.',
                ]);
            }

            $saleTransactions = InventoryTransaction::query()
                ->where('reference_type', Sale::class)
                ->where('reference_id', $sale->id)
                ->where('transaction_type', 'SALE')
                ->lockForUpdate()
                ->get();

            if ($saleTransactions->isEmpty()) {
                throw ValidationException::withMessages([
                    'sale' => 'No posted sale transactions were found for this sale.',
                ]);
            }

            $saleWarehouseId = $saleTransactions->first()?->warehouse_id;

            if ($saleWarehouseId !== $warehouseId) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Return warehouse must match the original sale warehouse.',
                ]);
            }

            $soldByVariant = $sale->items
                ->groupBy('variant_id')
                ->map(fn (Collection $rows): float => (float) $rows->sum('quantity'));

            $returnedByVariant = InventoryTransaction::query()
                ->where('reference_type', Sale::REFERENCE_RETURN)
                ->where('reference_id', $sale->id)
                ->where('transaction_type', 'SALE_RETURN')
                ->selectRaw('variant_id, COALESCE(SUM(quantity), 0) as returned_qty')
                ->groupBy('variant_id')
                ->pluck('returned_qty', 'variant_id')
                ->map(fn ($quantity): float => (float) $quantity);

            $returnRevenueDelta = 0.0;

            foreach ($items as $item) {
                $variantId = (string) $item['variant_id'];
                $returnQty = (float) $item['quantity'];

                $alreadyReturned = (float) ($returnedByVariant[$variantId] ?? 0.0);
                $soldQty = (float) ($soldByVariant[$variantId] ?? 0.0);

                if ($soldQty <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'The selected variant was not part of this sale.',
                    ]);
                }

                if ($alreadyReturned + $returnQty > $soldQty) {
                    throw ValidationException::withMessages([
                        'items' => 'Return quantity exceeds remaining sold quantity for one or more variants.',
                    ]);
                }

                $weightedUnitPrice = $this->resolveWeightedSalePrice($sale->items, $variantId);
                $returnRevenueDelta += round($returnQty * $weightedUnitPrice, 2);

                $totalReturnCost = $this->reverseCogsForSaleVariant(
                    sale: $sale,
                    variantId: $variantId,
                    returnQuantity: $returnQty,
                    warehouseId: $warehouseId,
                );

                InventoryTransaction::query()->create([
                    'variant_id' => $variantId,
                    'warehouse_id' => $warehouseId,
                    'transaction_type' => 'SALE_RETURN',
                    'quantity' => $returnQty,
                    'unit_cost' => $returnQty > 0 ? round($totalReturnCost / $returnQty, 4) : 0,
                    'reference_type' => Sale::REFERENCE_RETURN,
                    'reference_id' => $sale->id,
                    'transaction_date' => $returnDate ?? now()->toDateString(),
                    'created_by' => $userId,
                ]);
            }

            $sale->update([
                'total_amount' => max(0, round((float) $sale->total_amount - $returnRevenueDelta, 2)),
                'notes' => $this->appendNote((string) ($sale->notes ?? ''), $notes),
            ]);

            return $sale->fresh(['items.cogsEntries']) ?? $sale;
        });
    }

    public function voidPurchase(string $purchaseId, ?string $userId, ?string $reason = null, ?string $voidDate = null): Purchase
    {
        return DB::transaction(function () use ($purchaseId, $userId, $reason, $voidDate): Purchase {
            $purchase = Purchase::query()->with('items')->lockForUpdate()->findOrFail($purchaseId);

            if ($purchase->status === Purchase::STATUS_VOIDED) {
                throw ValidationException::withMessages([
                    'purchase' => 'Purchase is already voided.',
                ]);
            }

            $purchaseTransactions = InventoryTransaction::query()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->where('transaction_type', 'PURCHASE')
                ->lockForUpdate()
                ->get();

            if ($purchaseTransactions->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase' => 'No purchase inventory transactions were found for this purchase.',
                ]);
            }

            foreach ($purchaseTransactions as $transaction) {
                $quantityToReverse = abs((float) $transaction->quantity);

                $this->consumeCostLayers((string) $transaction->variant_id, (string) $transaction->warehouse_id, $quantityToReverse);

                InventoryTransaction::query()->create([
                    'variant_id' => $transaction->variant_id,
                    'warehouse_id' => $transaction->warehouse_id,
                    'transaction_type' => 'PURCHASE_RETURN',
                    'quantity' => -$quantityToReverse,
                    'unit_cost' => $transaction->unit_cost,
                    'reference_type' => Purchase::REFERENCE_VOID,
                    'reference_id' => $purchase->id,
                    'transaction_date' => $voidDate ?? now()->toDateString(),
                    'created_by' => $userId,
                ]);
            }

            $purchase->update([
                'status' => Purchase::STATUS_VOIDED,
                'reference_type' => Purchase::REFERENCE_VOID,
                'reference_id' => $purchase->id,
                'notes' => $this->appendNote((string) ($purchase->notes ?? ''), $reason),
            ]);

            return $purchase->fresh(['items']) ?? $purchase;
        });
    }

    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    public function returnPurchase(string $purchaseId, string $warehouseId, array $items, ?string $userId, ?string $notes = null, ?string $returnDate = null): Purchase
    {
        return DB::transaction(function () use ($purchaseId, $warehouseId, $items, $userId, $notes, $returnDate): Purchase {
            $purchase = Purchase::query()->with('items')->lockForUpdate()->findOrFail($purchaseId);

            if ($purchase->status !== Purchase::STATUS_POSTED) {
                throw ValidationException::withMessages([
                    'purchase' => 'Only posted purchases can be returned.',
                ]);
            }

            $purchaseTransactions = InventoryTransaction::query()
                ->where('reference_type', Purchase::class)
                ->where('reference_id', $purchase->id)
                ->where('transaction_type', 'PURCHASE')
                ->lockForUpdate()
                ->get();

            if ($purchaseTransactions->isEmpty()) {
                throw ValidationException::withMessages([
                    'purchase' => 'No posted purchase transactions were found for this purchase.',
                ]);
            }

            $purchaseWarehouseId = $purchaseTransactions->first()?->warehouse_id;

            if ($purchaseWarehouseId !== $warehouseId) {
                throw ValidationException::withMessages([
                    'warehouse_id' => 'Return warehouse must match the original purchase warehouse.',
                ]);
            }

            $purchasedByVariant = $purchase->items
                ->groupBy('variant_id')
                ->map(fn (Collection $rows): float => (float) $rows->sum('quantity'));

            $returnedByVariant = InventoryTransaction::query()
                ->where('reference_type', Purchase::REFERENCE_RETURN)
                ->where('reference_id', $purchase->id)
                ->where('transaction_type', 'PURCHASE_RETURN')
                ->selectRaw('variant_id, COALESCE(SUM(ABS(quantity)), 0) as returned_qty')
                ->groupBy('variant_id')
                ->pluck('returned_qty', 'variant_id')
                ->map(fn ($quantity): float => (float) $quantity);

            $returnAmountDelta = 0.0;

            foreach ($items as $item) {
                $variantId = (string) $item['variant_id'];
                $returnQty = (float) $item['quantity'];

                $alreadyReturned = (float) ($returnedByVariant[$variantId] ?? 0.0);
                $purchasedQty = (float) ($purchasedByVariant[$variantId] ?? 0.0);

                if ($purchasedQty <= 0) {
                    throw ValidationException::withMessages([
                        'items' => 'The selected variant was not part of this purchase.',
                    ]);
                }

                if ($alreadyReturned + $returnQty > $purchasedQty) {
                    throw ValidationException::withMessages([
                        'items' => 'Return quantity exceeds remaining purchased quantity for one or more variants.',
                    ]);
                }

                $weightedUnitCost = $this->resolveWeightedPurchaseCost($purchase->items, $variantId);
                $returnAmountDelta += round($returnQty * $weightedUnitCost, 2);

                $consumedCost = $this->consumeCostLayers($variantId, $warehouseId, $returnQty);

                InventoryTransaction::query()->create([
                    'variant_id' => $variantId,
                    'warehouse_id' => $warehouseId,
                    'transaction_type' => 'PURCHASE_RETURN',
                    'quantity' => -$returnQty,
                    'unit_cost' => $returnQty > 0 ? round($consumedCost / $returnQty, 4) : 0,
                    'reference_type' => Purchase::REFERENCE_RETURN,
                    'reference_id' => $purchase->id,
                    'transaction_date' => $returnDate ?? now()->toDateString(),
                    'created_by' => $userId,
                ]);
            }

            $purchase->update([
                'total_amount' => max(0, round((float) $purchase->total_amount - $returnAmountDelta, 2)),
                'notes' => $this->appendNote((string) ($purchase->notes ?? ''), $notes),
            ]);

            return $purchase->fresh(['items']) ?? $purchase;
        });
    }

    /**
     * @param  Collection<int, CogsEntry>  $entries
     */
    private function reverseCogsEntries(Collection $entries, string $warehouseId, ?string $userId, ?string $reverseDate = null): void
    {
        foreach ($entries as $entry) {
            CogsEntry::query()->create([
                'sale_item_id' => $entry->sale_item_id,
                'variant_id' => $entry->variant_id,
                'quantity' => -abs((float) $entry->quantity),
                'unit_cost' => $entry->unit_cost,
                'total_cost' => -abs((float) $entry->total_cost),
                'costing_method' => $entry->costing_method,
                'source_layer_id' => $entry->source_layer_id,
            ]);

            $quantityToRestore = abs((float) $entry->quantity);
            $totalCostToRestore = abs((float) $entry->total_cost);

            $this->restoreLayerAndValuation(
                variantId: (string) $entry->variant_id,
                warehouseId: $warehouseId,
                sourceLayerId: $entry->source_layer_id,
                quantity: $quantityToRestore,
                unitCost: (float) $entry->unit_cost,
                totalCost: $totalCostToRestore,
                costingMethod: (string) $entry->costing_method,
            );

            InventoryTransaction::query();
        }
    }

    private function reverseCogsForSaleVariant(Sale $sale, string $variantId, float $returnQuantity, string $warehouseId): float
    {
        $saleItemIds = $sale->items->pluck('id');

        $positiveEntries = CogsEntry::query()
            ->whereIn('sale_item_id', $saleItemIds)
            ->where('variant_id', $variantId)
            ->where('quantity', '>', 0)
            ->orderBy('created_at')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($positiveEntries->isEmpty()) {
            throw ValidationException::withMessages([
                'items' => 'No COGS records were found for one or more returned variants.',
            ]);
        }

        $returnedByKey = CogsEntry::query()
            ->whereIn('sale_item_id', $saleItemIds)
            ->where('variant_id', $variantId)
            ->where('quantity', '<', 0)
            ->selectRaw("sale_item_id, COALESCE(source_layer_id, 'NO_LAYER') as source_layer_key, unit_cost, costing_method, SUM(ABS(quantity)) as returned_qty")
            ->groupBy('sale_item_id', 'source_layer_key', 'unit_cost', 'costing_method')
            ->get()
            ->mapWithKeys(fn ($row): array => [
                $this->cogsKey((string) $row->sale_item_id, (string) $row->source_layer_key, (float) $row->unit_cost, (string) $row->costing_method) => (float) $row->returned_qty,
            ]);

        $remaining = $returnQuantity;
        $totalReversedCost = 0.0;

        foreach ($positiveEntries as $entry) {
            if ($remaining <= 0) {
                break;
            }

            $sourceLayerId = (string) ($entry->source_layer_id ?? 'NO_LAYER');
            $entryKey = $this->cogsKey((string) $entry->sale_item_id, $sourceLayerId, (float) $entry->unit_cost, (string) $entry->costing_method);
            $alreadyReturnedFromEntry = (float) ($returnedByKey[$entryKey] ?? 0.0);
            $entryAvailable = max(0.0, (float) $entry->quantity - $alreadyReturnedFromEntry);

            if ($entryAvailable <= 0) {
                continue;
            }

            $portion = min($entryAvailable, $remaining);
            $portionCost = round($portion * (float) $entry->unit_cost, 2);

            CogsEntry::query()->create([
                'sale_item_id' => $entry->sale_item_id,
                'variant_id' => $entry->variant_id,
                'quantity' => -$portion,
                'unit_cost' => $entry->unit_cost,
                'total_cost' => -$portionCost,
                'costing_method' => $entry->costing_method,
                'source_layer_id' => $entry->source_layer_id,
            ]);

            $this->restoreLayerAndValuation(
                variantId: (string) $entry->variant_id,
                warehouseId: $warehouseId,
                sourceLayerId: $entry->source_layer_id,
                quantity: $portion,
                unitCost: (float) $entry->unit_cost,
                totalCost: $portionCost,
                costingMethod: (string) $entry->costing_method,
            );

            $remaining -= $portion;
            $totalReversedCost += $portionCost;
        }

        if ($remaining > 0) {
            throw ValidationException::withMessages([
                'items' => 'Unable to reverse full return quantity for one or more variants.',
            ]);
        }

        return $totalReversedCost;
    }

    private function restoreLayerAndValuation(
        string $variantId,
        string $warehouseId,
        ?string $sourceLayerId,
        float $quantity,
        float $unitCost,
        float $totalCost,
        string $costingMethod,
    ): void {
        if (is_string($sourceLayerId) && $sourceLayerId !== '') {
            $layer = InventoryCostLayer::query()->lockForUpdate()->find($sourceLayerId);

            if ($layer) {
                $layer->update([
                    'remaining_qty' => (float) $layer->remaining_qty + $quantity,
                ]);
            }
        } else {
            InventoryCostLayer::query()->create([
                'variant_id' => $variantId,
                'warehouse_id' => $warehouseId,
                'remaining_qty' => $quantity,
                'unit_cost' => $unitCost,
                'source_transaction_id' => null,
            ]);
        }

        if ($costingMethod === CostingService::WEIGHTED_AVERAGE) {
            $this->adjustInventoryValuation($variantId, $warehouseId, $quantity, $totalCost);
        }
    }

    private function consumeCostLayers(string $variantId, string $warehouseId, float $quantity): float
    {
        $direction = $this->costingService->resolveMethod() === CostingService::LIFO ? 'desc' : 'asc';

        $layers = InventoryCostLayer::query()
            ->where('variant_id', $variantId)
            ->where('warehouse_id', $warehouseId)
            ->where('remaining_qty', '>', 0)
            ->orderBy('created_at', $direction)
            ->orderBy('id', $direction)
            ->lockForUpdate()
            ->get();

        $availableQuantity = (float) $layers->sum('remaining_qty');

        if ($availableQuantity < $quantity) {
            throw ValidationException::withMessages([
                'items' => 'Not enough stock to complete this purchase reversal or return.',
            ]);
        }

        $remaining = $quantity;
        $totalCost = 0.0;

        foreach ($layers as $layer) {
            if ($remaining <= 0) {
                break;
            }

            $portion = min((float) $layer->remaining_qty, $remaining);

            $layer->update([
                'remaining_qty' => (float) $layer->remaining_qty - $portion,
            ]);

            $portionCost = round($portion * (float) $layer->unit_cost, 2);
            $totalCost += $portionCost;
            $remaining -= $portion;
        }

        return $totalCost;
    }

    /**
     * @param  Collection<int, mixed>  $saleItems
     */
    private function resolveWeightedSalePrice(Collection $saleItems, string $variantId): float
    {
        $matchingItems = $saleItems->where('variant_id', $variantId);
        $totalQty = (float) $matchingItems->sum('quantity');

        if ($totalQty <= 0) {
            return 0;
        }

        return round((float) $matchingItems->sum('total_price') / $totalQty, 4);
    }

    /**
     * @param  Collection<int, mixed>  $purchaseItems
     */
    private function resolveWeightedPurchaseCost(Collection $purchaseItems, string $variantId): float
    {
        $matchingItems = $purchaseItems->where('variant_id', $variantId);
        $totalQty = (float) $matchingItems->sum('quantity');

        if ($totalQty <= 0) {
            return 0;
        }

        return round((float) $matchingItems->sum('total_cost') / $totalQty, 4);
    }

    private function appendNote(string $currentNote, ?string $noteToAppend): ?string
    {
        $trimmedCurrent = trim($currentNote);
        $trimmedNew = trim((string) $noteToAppend);

        if ($trimmedNew === '') {
            return $trimmedCurrent === '' ? null : $trimmedCurrent;
        }

        if ($trimmedCurrent === '') {
            return $trimmedNew;
        }

        return $trimmedCurrent.PHP_EOL.$trimmedNew;
    }

    private function cogsKey(string $saleItemId, string $sourceLayerId, float $unitCost, string $costingMethod): string
    {
        return implode('|', [$saleItemId, $sourceLayerId, (string) round($unitCost, 4), $costingMethod]);
    }

    private function adjustInventoryValuation(string $variantId, string $warehouseId, float $quantityDelta, float $costDelta): void
    {
        $valuation = DB::table('inventory_valuation')
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
}
