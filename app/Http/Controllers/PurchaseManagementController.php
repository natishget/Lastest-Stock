<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\StorePurchaseRequest;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseManagementController extends Controller
{
    public function __construct(private readonly CostingService $costingService) {}

    public function index(Request $request): Response
    {
        $this->authorizeManagementAccess($request);

        $purchases = Purchase::query()
            ->with(['items.variant.product'])
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Purchase $purchase): array => [
                'id' => $purchase->id,
                'supplier_name' => $purchase->supplier_name,
                'invoice_number' => $purchase->invoice_number,
                'purchase_date' => $purchase->purchase_date,
                'total_amount' => $purchase->total_amount,
                'item_count' => $purchase->items->count(),
                'items' => $purchase->items->map(fn (PurchaseItem $item): array => [
                    'variant_id' => $item->variant_id,
                    'product_name' => $item->variant?->product?->name,
                    'variant_label' => $this->variantLabel($item->variant),
                    'quantity' => $item->quantity,
                    'unit_cost' => $item->unit_cost,
                    'total_cost' => $item->total_cost,
                ])->all(),
                'created_at' => $purchase->created_at,
            ]);

        return Inertia::render('purchases/index', [
            'purchases' => $purchases,
            'variantOptions' => $this->variantOptions(),
            'warehouses' => $this->warehouseOptions(),
        ]);
    }

    public function store(StorePurchaseRequest $request): RedirectResponse
    {
        $this->authorizeManagementAccess($request);

        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $user): void {
            $totalAmount = 0.0;
            $warehouseId = $validated['warehouse_id'];

            $purchase = Purchase::query()->create([
                'supplier_name' => $validated['supplier_name'] ?? null,
                'invoice_number' => $validated['invoice_number'] ?? null,
                'purchase_date' => $validated['purchase_date'] ?? now()->toDateString(),
                'created_by' => $user?->id,
                'total_amount' => 0,
            ]);

            foreach ($validated['items'] as $itemData) {
                $quantity = (float) $itemData['quantity'];
                $unitCost = (float) $itemData['unit_cost'];
                $lineTotal = round($quantity * $unitCost, 2);
                $totalAmount += $lineTotal;

                $purchaseItem = $purchase->items()->create([
                    'variant_id' => $itemData['variant_id'],
                    'quantity' => $quantity,
                    'unit_cost' => $unitCost,
                    'total_cost' => $lineTotal,
                ]);

                InventoryTransaction::query()->create([
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

                $this->costingService->recordPurchase($itemData['variant_id'], $warehouseId, $quantity, $unitCost);
            }

            $purchase->update(['total_amount' => $totalAmount]);
        });

        return back()->with('success', 'Purchase created successfully.');
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function variantOptions(): array
    {
        return ProductVariant::query()
            ->with('product')
            ->orderBy('sku')
            ->get()
            ->map(fn (ProductVariant $variant): array => [
                'id' => $variant->id,
                'label' => $this->variantLabel($variant),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function warehouseOptions(): array
    {
        return Warehouse::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (Warehouse $warehouse): array => [
                'id' => $warehouse->id,
                'name' => $warehouse->name,
            ])
            ->all();
    }

    private function variantLabel(?ProductVariant $variant): string
    {
        if (! $variant) {
            return '-';
        }

        $parts = array_filter([
            $variant->product?->name,
            $variant->sku,
            $variant->color,
            $variant->origin,
            $variant->size,
        ]);

        return implode(' · ', $parts);
    }

    private function authorizeManagementAccess(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, [User::ROLE_ADMIN, User::ROLE_SALES], true),
            403,
        );
    }
}
