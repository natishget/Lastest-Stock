<?php

namespace App\Services;

use App\Models\InventoryTransaction;
use App\Models\Purchase;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class PurchaseService
{
    public function __construct(private readonly CostingService $costingService) {}

    /**
     * @param  array<string, mixed>  $validated
     */
    public function createPurchase(array $validated, ?User $user): Purchase
    {
        return DB::transaction(function () use ($validated, $user): Purchase {
            $totalAmount = 0.0;
            $warehouseId = (string) $validated['warehouse_id'];

            $purchase = Purchase::query()->create([
                'supplier_name' => $validated['supplier_name'] ?? null,
                'invoice_number' => $validated['invoice_number'] ?? null,
                'purchase_date' => $validated['purchase_date'] ?? now()->toDateString(),
                'status' => Purchase::STATUS_POSTED,
                'notes' => $validated['notes'] ?? null,
                'created_by' => $user?->id,
                'total_amount' => 0,
            ]);

            foreach ($validated['items'] as $itemData) {
                $quantity = (float) $itemData['quantity'];
                $unitCost = (float) $itemData['unit_cost'];
                $lineTotal = round($quantity * $unitCost, 2);
                $totalAmount += $lineTotal;

                $purchase->items()->create([
                    'variant_id' => $itemData['variant_id'],
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotal,
                ]);

                $inventoryTransaction = InventoryTransaction::query()->create([
                    'variant_id' => $itemData['variant_id'],
                    'warehouse_id' => $warehouseId,
                    'transaction_type' => 'PURCHASE',
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'reference_type' => Purchase::class,
                    'reference_id' => $purchase->id,
                    'transaction_date' => $validated['purchase_date'] ?? now()->toDateString(),
                    'created_by' => $user?->id,
                ]);

                $this->costingService->recordPurchase(
                    variantId: (string) $itemData['variant_id'],
                    warehouseId: $warehouseId,
                    quantity: $quantity,
                    unitCost: $unitCost,
                    sourceTransactionId: $inventoryTransaction->id,
                );
            }

            $purchase->update(['total_amount' => $totalAmount]);

            return $purchase->fresh(['items']) ?? $purchase;
        });
    }

    /**
     * @param  array<string, mixed>  $validated
     */
    public function updateMetadata(Purchase $purchase, array $validated): Purchase
    {
        $purchase->update([
            'supplier_name' => $validated['supplier_name'] ?? null,
            'notes' => $validated['notes'] ?? null,
        ]);

        return $purchase->fresh() ?? $purchase;
    }
}
