<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\StoreSaleRequest;
use App\Models\InventoryCostLayer;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CostingService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesManagementController extends Controller
{
    public function __construct(private readonly CostingService $costingService) {}

    public function index(Request $request): Response
    {
        $this->authorizeManagementAccess($request);

        $sales = Sale::query()
            ->with(['items.variant.product'])
            ->latest('created_at')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Sale $sale): array => [
                'id' => $sale->id,
                'customer_name' => $sale->customer_name,
                'sale_date' => $sale->sale_date,
                'total_amount' => $sale->total_amount,
                'item_count' => $sale->items->count(),
                'items' => $sale->items->map(fn (SaleItem $item): array => [
                    'variant_id' => $item->variant_id,
                    'product_name' => $item->variant?->product?->name,
                    'variant_label' => $this->variantLabel($item->variant),
                    'quantity' => $item->quantity,
                    'selling_price' => $item->selling_price,
                    'total_price' => $item->total_price,
                ])->all(),
                'created_at' => $sale->created_at,
            ]);

        return Inertia::render('sales/index', [
            'sales' => $sales,
            'variantOptions' => $this->variantOptions(),
            'availableQuantities' => $this->availableQuantities(),
            'warehouses' => $this->warehouseOptions(),
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $this->authorizeManagementAccess($request);

        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $user): void {
            $warehouseId = $validated['warehouse_id'];

            $sale = Sale::query()->create([
                'customer_name' => $validated['customer_name'] ?? null,
                'sale_date' => $validated['sale_date'] ?? now()->toDateString(),
                'created_by' => $user?->id,
                'total_amount' => 0,
            ]);

            $totalAmount = 0.0;

            foreach ($validated['items'] as $itemData) {
                $variantId = $itemData['variant_id'];
                $quantity = (float) $itemData['quantity'];
                $sellingPrice = (float) $itemData['selling_price'];
                $lineTotal = round($quantity * $sellingPrice, 2);

                $saleItem = $sale->items()->create([
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                    'selling_price' => $sellingPrice,
                    'total_price' => $lineTotal,
                ]);

                $totalCost = $this->costingService->recordSaleItemCogs($saleItem, $warehouseId);

                InventoryTransaction::query()->create([
                    'variant_id' => $variantId,
                    'warehouse_id' => $warehouseId,
                    'transaction_type' => 'SALE',
                    'quantity' => -$quantity,
                    'unit_cost' => $quantity > 0 ? round($totalCost / $quantity, 4) : 0,
                    'reference_type' => Sale::class,
                    'reference_id' => $sale->id,
                    'transaction_date' => $validated['sale_date'] ?? now()->toDateString(),
                    'created_by' => $user?->id,
                ]);

                $totalAmount += $lineTotal;
            }

            $sale->update(['total_amount' => $totalAmount]);
        });

        return back()->with('success', 'Sale created successfully.');
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
     * @return array<string, string>
     */
    private function availableQuantities(): array
    {
        return InventoryCostLayer::query()
            ->selectRaw("CONCAT(warehouse_id, ':', variant_id) as warehouse_variant_key")
            ->selectRaw('COALESCE(SUM(remaining_qty), 0) as available_qty')
            ->whereNotNull('warehouse_id')
            ->groupBy('warehouse_id', 'variant_id')
            ->pluck('available_qty', 'warehouse_variant_key')
            ->map(fn ($quantity): string => (string) $quantity)
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
