<?php

namespace App\Http\Controllers;

use App\Http\Requests\Warehouse\StoreWarehouseRequest;
use App\Http\Requests\Warehouse\UpdateWarehouseRequest;
use App\Models\Warehouse;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class WarehouseController extends Controller
{
    public function index(): JsonResponse
    {
        $warehouses = Warehouse::query()
            ->orderBy('name')
            ->get(['id', 'name', 'location']);

        return response()->json([
            'data' => $warehouses,
        ]);
    }

    public function store(StoreWarehouseRequest $request): JsonResponse
    {
        $warehouse = Warehouse::query()->create($request->validated());

        return response()->json([
            'data' => $warehouse,
            'message' => 'Warehouse created successfully.',
        ], 201);
    }

    public function show(Warehouse $warehouse): JsonResponse
    {
        return response()->json([
            'data' => $warehouse->only(['id', 'name', 'location']),
        ]);
    }

    public function update(UpdateWarehouseRequest $request, Warehouse $warehouse): JsonResponse
    {
        $warehouse->update($request->validated());

        return response()->json([
            'data' => $warehouse->fresh()->only(['id', 'name', 'location']),
            'message' => 'Warehouse updated successfully.',
        ]);
    }

    public function destroy(Warehouse $warehouse): JsonResponse
    {
        if ($warehouse->inventoryTransactions()->exists()) {
            throw ValidationException::withMessages([
                'warehouse' => 'This warehouse cannot be deleted because it has inventory transactions.',
            ]);
        }

        $warehouse->delete();

        return response()->json([
            'message' => 'Warehouse deleted successfully.',
        ]);
    }

    public function stock(Warehouse $warehouse): JsonResponse
    {
        $stock = DB::table('inventory_transactions as it')
            ->join('product_variants as pv', 'pv.id', '=', 'it.variant_id')
            ->join('products as p', 'p.id', '=', 'pv.product_id')
            ->where('it.warehouse_id', $warehouse->id)
            ->groupBy('pv.id', 'p.name', 'pv.color', 'pv.origin')
            ->havingRaw('SUM(it.quantity) != 0')
            ->orderBy('p.name')
            ->selectRaw('pv.id as variant_id, p.name as product_name, pv.color, pv.origin, SUM(it.quantity) as total_stock')
            ->get();

        return response()->json([
            'data' => $stock,
        ]);
    }
}
