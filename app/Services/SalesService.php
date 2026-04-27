<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Sale;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function __construct(private readonly CostingService $costingService) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createSale(array $validated, ?User $user): Sale
    {
        return DB::transaction(function () use ($validated, $user): Sale {
            $warehouseId = (string) $validated['warehouse_id'];

            $sale = Sale::query()->create([
                'customer_name' => $validated['customer_name'] ?? null,
                'sale_date' => $validated['sale_date'] ?? now()->toDateString(),
                'status' => Sale::STATUS_POSTED,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $user?->id,
                'total_amount' => 0,
            ]);

            $totalAmount = 0.0;
            $createdSaleItems = [];

            foreach ($validated['items'] as $itemData) {
                $variantId = (string) $itemData['variant_id'];
                $quantity = (float) $itemData['quantity'];
                $sellingPrice = (float) $itemData['selling_price'];
                $lineTotal = round($quantity * $sellingPrice, 2);

                $saleItem = $sale->items()->create([
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                    'selling_price' => $sellingPrice,
                    'total_price' => $lineTotal,
                ]);

                $createdSaleItems[] = $saleItem;

                $totalAmount += $lineTotal;
            }

            $totalCostBySaleItem = $this->costingService->applyCostingToSale($sale, $warehouseId);

            foreach ($createdSaleItems as $saleItem) {
                $quantity = (float) $saleItem->quantity;
                $totalCost = (float) ($totalCostBySaleItem[$saleItem->id] ?? 0);

                InventoryTransaction::query()->create([
                    'variant_id' => $saleItem->variant_id,
                    'warehouse_id' => $warehouseId,
                    'transaction_type' => 'SALE',
                    'quantity' => -$quantity,
                    'unit_cost' => $quantity > 0 ? round($totalCost / $quantity, 4) : 0,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'transaction_date' => $validated['sale_date'] ?? now()->toDateString(),
                    'created_by' => $user?->id,
                ]);
            }

            $sale->update(['total_amount' => $totalAmount]);

            return $sale->fresh(['items']) ?? $sale;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateMetadata(Sale $sale, array $validated): Sale
    {
        $sale->update([
            'customer_name' => $validated['customer_name'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return $sale->fresh() ?? $sale;
    }
}
