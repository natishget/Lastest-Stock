<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\CreateSaleReturnRequest;
use App\Http\Requests\Order\StoreSaleRequest;
use App\Http\Requests\Order\UpdateSaleRequest;
use App\Http\Requests\Order\VoidTransactionRequest;
use App\Models\InventoryCostLayer;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CorrectionService;
use App\Services\CostingService;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

class SalesManagementController extends Controller
{
    public function __construct(
        private readonly CostingService $costingService,
        private readonly SalesService $salesService,
        private readonly CorrectionService $correctionService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeViewAccess($request);

        $search = trim((string) $request->string('search')->toString());
        $status = trim((string) $request->string('status')->toString());
        $date = trim((string) $request->string('date')->toString());
        $dateOrder = strtolower(trim((string) $request->string('date_order')->toString())) === 'asc' ? 'asc' : 'desc';

        $sales = Sale::query()
            ->with(['items.variant.product'])
            ->when($search !== '', function ($query) use ($search): void {
                $query->where('customer_name', 'like', "%{$search}%");
            })
            ->when($status !== '', function ($query) use ($status): void {
                $query->where('status', $status);
            })
            ->when($date !== '', function ($query) use ($date): void {
                $query->whereDate('sale_date', $date);
            })
            ->orderBy('sale_date', $dateOrder)
            ->orderBy('created_at', 'desc')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (Sale $sale): array => [
                'id' => $sale->id,
                'customer_name' => $sale->customer_name,
                'sale_date' => $sale->sale_date,
                'total_amount' => $sale->total_amount,
                'status' => $sale->status ?? Sale::STATUS_POSTED,
                'notes' => $sale->notes,
                'warehouse_id' => $this->resolveSaleWarehouse($sale),
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
            'filters' => [
                'search' => $search,
                'status' => $status,
                'date' => $date,
                'date_order' => $dateOrder,
            ],
            'variantOptions' => $this->variantOptions(),
            'availableQuantities' => $this->availableQuantities(),
            'warehouses' => $this->warehouseOptions(),
        ]);
    }

    public function store(StoreSaleRequest $request): RedirectResponse
    {
        $this->authorizeManagementAccess($request);

        $this->salesService->createSale($request->validated(), $request->user());

        return back()->with('success', 'Sale created successfully.');
    }

    public function update(UpdateSaleRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorizeManagementAccess($request);

        $this->salesService->updateMetadata($sale, $request->validated());

        return back()->with('success', 'Sale details updated successfully.');
    }

    public function void(VoidTransactionRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorizeManagementAccess($request);

        $validated = $request->validated();

        $this->correctionService->voidSale(
            saleId: $sale->id,
            userId: $request->user()?->id,
            reason: $validated['reason'] ?? null,
            voidDate: $validated['void_date'] ?? null,
        );

        return back()->with('success', 'Sale voided successfully using reversal entries.');
    }

    public function returnItems(CreateSaleReturnRequest $request, Sale $sale): RedirectResponse
    {
        $this->authorizeManagementAccess($request);

        $validated = $request->validated();

        $this->correctionService->returnSale(
            saleId: $sale->id,
            warehouseId: (string) $validated['warehouse_id'],
            items: $validated['items'],
            userId: $request->user()?->id,
            notes: $validated['notes'] ?? null,
            returnDate: $validated['return_date'] ?? null,
        );

        return back()->with('success', 'Sale return recorded successfully.');
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
        $warehouseVariantExpression = DB::connection()->getDriverName() === 'sqlite'
            ? "warehouse_id || ':' || variant_id"
            : "CONCAT(warehouse_id, ':', variant_id)";

        return InventoryCostLayer::query()
            ->selectRaw("{$warehouseVariantExpression} as warehouse_variant_key")
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

    private function authorizeViewAccess(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, [User::ROLE_ADMIN, User::ROLE_SALES, User::ROLE_AUDITOR], true),
            403,
        );
    }

    private function resolveSaleWarehouse(Sale $sale): ?string
    {
        return InventoryTransaction::query()
            ->where('reference_type', Sale::class)
            ->where('reference_id', $sale->id)
            ->where('transaction_type', 'SALE')
            ->value('warehouse_id');
    }
}
