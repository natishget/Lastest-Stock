<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\StoreSaleRequest;
use App\Models\CogsEntry;
use App\Models\InventoryCostLayer;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class SalesManagementController extends Controller
{
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
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $this->authorizeManagementAccess($request);

        $validated = $request->validated();
        $user = $request->user();

        DB::transaction(function () use ($validated, $user): void {
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

                $remainingQuantity = $quantity;
                $totalCost = 0.0;

                $costLayers = InventoryCostLayer::query()
                    ->where('variant_id', $variantId)
                    ->where('remaining_qty', '>', 0)
                    ->orderBy('created_at')
                    ->lockForUpdate()
                    ->get();

                $availableQuantity = (float) $costLayers->sum('remaining_qty');

                if ($availableQuantity < $quantity) {
                    throw ValidationException::withMessages([
                        'items' => 'Not enough available stock for one of the selected variants.',
                    ]);
                }

                $saleItem = $sale->items()->create([
                    'variant_id' => $variantId,
                    'quantity' => $quantity,
                    'selling_price' => $sellingPrice,
                    'total_price' => $lineTotal,
                ]);

                foreach ($costLayers as $layer) {
                    if ($remainingQuantity <= 0) {
                        break;
                    }

                    $consumedQuantity = min((float) $layer->remaining_qty, $remainingQuantity);
                    $layerCost = round($consumedQuantity * (float) $layer->unit_cost, 2);

                    CogsEntry::query()->create([
                        'sale_item_id' => $saleItem->id,
                        'variant_id' => $variantId,
                        'quantity' => $consumedQuantity,
                        'unit_cost' => $layer->unit_cost,
                        'total_cost' => $layerCost,
                        'costing_method' => 'FIFO',
                        'source_layer_id' => $layer->id,
                    ]);

                    $layer->update([
                        'remaining_qty' => (float) $layer->remaining_qty - $consumedQuantity,
                    ]);

                    $totalCost += $layerCost;
                    $remainingQuantity -= $consumedQuantity;
                }

                InventoryTransaction::query()->create([
                    'variant_id' => $variantId,
                    'warehouse_id' => null,
                    'transaction_type' => 'SALE',
                    'quantity' => $quantity,
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
            ->select('variant_id', DB::raw('COALESCE(SUM(remaining_qty), 0) as available_qty'))
            ->groupBy('variant_id')
            ->pluck('available_qty', 'variant_id')
            ->map(fn ($quantity): string => (string) $quantity)
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
