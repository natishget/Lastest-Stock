<?php

use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;

test('admin can manage warehouses through api endpoints', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $createResponse = $this->actingAs($admin)->postJson('/api/warehouses', [
        'name' => 'Central Depot',
        'location' => 'Addis Ababa',
    ]);

    $createResponse->assertCreated()->assertJsonPath('data.name', 'Central Depot');

    $warehouseId = $createResponse->json('data.id');

    $this->actingAs($admin)->getJson('/api/warehouses')->assertOk()->assertJsonCount(1, 'data');

    $this->actingAs($admin)->getJson("/api/warehouses/{$warehouseId}")->assertOk()->assertJsonPath('data.id', $warehouseId);

    $this->actingAs($admin)->putJson("/api/warehouses/{$warehouseId}", [
        'name' => 'Central Depot Updated',
        'location' => 'Bole',
    ])->assertOk()->assertJsonPath('data.name', 'Central Depot Updated');

    $this->actingAs($admin)->deleteJson("/api/warehouses/{$warehouseId}")->assertOk();

    $this->assertDatabaseMissing('warehouses', [
        'id' => $warehouseId,
    ]);
});

test('warehouse cannot be deleted when it has inventory transactions', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = Warehouse::query()->create([
        'name' => 'Main Warehouse',
        'location' => 'HQ',
    ]);

    $product = Product::query()->create([
        'name' => 'Steel Rod',
        'base_unit' => 'Piece',
    ]);

    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'color' => 'Blue',
        'origin' => 'LOCAL',
        'sku' => 'ST-001',
        'thickness' => 1.0,
        'size' => 'M',
    ]);

    InventoryTransaction::query()->create([
        'variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'transaction_type' => 'PURCHASE',
        'quantity' => 8,
        'unit_cost' => 10,
        'reference_type' => 'test',
        'reference_id' => $warehouse->id,
        'transaction_date' => now()->toDateString(),
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->deleteJson("/api/warehouses/{$warehouse->id}")
        ->assertUnprocessable()
        ->assertJsonValidationErrors('warehouse');
});

test('warehouse stock endpoint returns aggregated stock per variant', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = Warehouse::query()->create([
        'name' => 'Main Warehouse',
        'location' => 'HQ',
    ]);

    $product = Product::query()->create([
        'name' => 'Cement Bag',
        'base_unit' => 'Bag',
    ]);

    $variant = ProductVariant::query()->create([
        'product_id' => $product->id,
        'color' => 'Gray',
        'origin' => 'IMPORTED',
        'sku' => 'CM-100',
        'thickness' => 1.0,
        'size' => '50kg',
    ]);

    InventoryTransaction::query()->create([
        'variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'transaction_type' => 'PURCHASE',
        'quantity' => 12,
        'unit_cost' => 11,
        'reference_type' => 'test',
        'reference_id' => $warehouse->id,
        'transaction_date' => now()->toDateString(),
        'created_by' => $admin->id,
    ]);

    InventoryTransaction::query()->create([
        'variant_id' => $variant->id,
        'warehouse_id' => $warehouse->id,
        'transaction_type' => 'SALE',
        'quantity' => -4,
        'unit_cost' => 11,
        'reference_type' => 'test',
        'reference_id' => $warehouse->id,
        'transaction_date' => now()->toDateString(),
        'created_by' => $admin->id,
    ]);

    $this->actingAs($admin)
        ->getJson("/api/warehouses/{$warehouse->id}/stock")
        ->assertOk()
        ->assertJsonPath('data.0.variant_id', $variant->id)
        ->assertJsonPath('data.0.product_name', 'Cement Bag')
        ->assertJsonPath('data.0.total_stock', 8);
});

test('non admin users cannot manage warehouse api', function () {
    $salesUser = User::factory()->create(['role' => User::ROLE_SALES]);

    $response = $this->actingAs($salesUser)->getJson('/api/warehouses');

    $response->assertForbidden();
});
