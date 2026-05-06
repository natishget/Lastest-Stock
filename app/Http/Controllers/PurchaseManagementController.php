<?php

namespace App\Http\Controllers;

use App\Http\Requests\Order\CreatePurchaseReturnRequest;
use App\Http\Requests\Order\StorePurchaseRequest;
use App\Http\Requests\Order\UpdatePurchaseRequest;
use App\Http\Requests\Order\VoidTransactionRequest;
use App\Models\InventoryTransaction;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\CorrectionService;
use App\Services\PurchaseService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PurchaseManagementController extends Controller
{
    public function __construct(
        private readonly PurchaseService $purchaseService,
        private readonly CorrectionService $correctionService,
    ) {}

    public function index(Request $request): Response
    {
        $this->authorizeViewAccess($request);

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
                'status' => $purchase->status ?? Purchase::STATUS_POSTED,
                'notes' => $purchase->notes,
                'warehouse_id' => $this->resolvePurchaseWarehouse($purchase),
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

        $this->purchaseService->createPurchase($request->validated(), $request->user());

        return back()->with('success', 'Purchase created successfully.');
    }

    public function update(UpdatePurchaseRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizeManagementAccess($request);

        $this->purchaseService->updateMetadata($purchase, $request->validated());

        return back()->with('success', 'Purchase details updated successfully.');
    }

    public function void(VoidTransactionRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizeManagementAccess($request);

        $validated = $request->validated();

        $this->correctionService->voidPurchase(
            purchaseId: $purchase->id,
            userId: $request->user()?->id,
            reason: $validated['reason'] ?? null,
            voidDate: $validated['void_date'] ?? null,
        );

        return back()->with('success', 'Purchase voided successfully using reversal entries.');
    }

    public function returnItems(CreatePurchaseReturnRequest $request, Purchase $purchase): RedirectResponse
    {
        $this->authorizeManagementAccess($request);

        $validated = $request->validated();

        $this->correctionService->returnPurchase(
            purchaseId: $purchase->id,
            warehouseId: (string) $validated['warehouse_id'],
            items: $validated['items'],
            userId: $request->user()?->id,
            notes: $validated['notes'] ?? null,
            returnDate: $validated['return_date'] ?? null,
        );

        return back()->with('success', 'Purchase return recorded successfully.');
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

    private function authorizeViewAccess(Request $request): void
    {
        abort_unless(
            in_array($request->user()?->role, [User::ROLE_ADMIN, User::ROLE_SALES, User::ROLE_AUDITOR], true),
            403,
        );
    }

    private function resolvePurchaseWarehouse(Purchase $purchase): ?string
    {
        return InventoryTransaction::query()
            ->where('reference_type', Purchase::class)
            ->where('reference_id', $purchase->id)
            ->where('transaction_type', 'PURCHASE')
            ->value('warehouse_id');
    }
}
