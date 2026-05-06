<?php

use App\Models\CogsEntry;
use App\Models\InventoryCostLayer;
use App\Models\InventoryTransaction;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;
use App\Models\Warehouse;

function createVariant(array $attributes = []): ProductVariant
{
    $product = Product::query()->create([
        'name' => $attributes['product_name'] ?? fake()->unique()->word(),
        'base_unit' => 'Piece',
    ]);

    return ProductVariant::query()->create([
        'product_id' => $product->id,
        'color' => $attributes['color'] ?? 'Blue',
        'origin' => $attributes['origin'] ?? 'LOCAL',
        'sku' => $attributes['sku'] ?? fake()->unique()->bothify('SKU-###'),
        'thickness' => $attributes['thickness'] ?? 1.0,
        'size' => $attributes['size'] ?? 'M',
    ]);
}

test('admin can create purchase with multiple items', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = Warehouse::query()->create([
        'name' => 'Main Warehouse',
        'location' => 'HQ',
    ]);
    $variantOne = createVariant(['sku' => 'PUR-001']);
    $variantTwo = createVariant(['sku' => 'PUR-002', 'color' => 'Red']);

    $response = $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Acme Supplies',
        'invoice_number' => 'INV-1001',
        'purchase_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variantOne->id,
                'quantity' => 10,
                'unit_cost' => 5,
            ],
            [
                'variant_id' => $variantTwo->id,
                'quantity' => 4,
                'unit_cost' => 8.5,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    expect(Purchase::query()->count())->toBe(1);
    expect(PurchaseItem::query()->count())->toBe(2);
    expect(InventoryCostLayer::query()->count())->toBe(2);
});

test('admin can create sale with multiple items and consume stock', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = Warehouse::query()->create([
        'name' => 'Main Warehouse',
        'location' => 'HQ',
    ]);
    $variantOne = createVariant(['sku' => 'SAL-001']);
    $variantTwo = createVariant(['sku' => 'SAL-002', 'color' => 'Red']);

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Acme Supplies',
        'purchase_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variantOne->id,
                'quantity' => 10,
                'unit_cost' => 5,
            ],
            [
                'variant_id' => $variantTwo->id,
                'quantity' => 6,
                'unit_cost' => 8,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Retail Buyer',
        'sale_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variantOne->id,
                'quantity' => 3,
                'selling_price' => 9,
            ],
            [
                'variant_id' => $variantTwo->id,
                'quantity' => 2,
                'selling_price' => 12,
            ],
        ],
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    expect(Sale::query()->count())->toBe(1);
    expect(SaleItem::query()->count())->toBe(2);
    expect(CogsEntry::query()->count())->toBe(2);
    expect(InventoryCostLayer::query()->where('warehouse_id', $warehouse->id)->where('variant_id', $variantOne->id)->sum('remaining_qty'))->toBe(7);
    expect(InventoryCostLayer::query()->where('warehouse_id', $warehouse->id)->where('variant_id', $variantTwo->id)->sum('remaining_qty'))->toBe(4);
});

test('sale is blocked when stock is insufficient', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = Warehouse::query()->create([
        'name' => 'Main Warehouse',
        'location' => 'HQ',
    ]);
    $variant = createVariant(['sku' => 'SAL-003']);

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Acme Supplies',
        'purchase_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 2,
                'unit_cost' => 5,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Retail Buyer',
        'sale_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 5,
                'selling_price' => 9,
            ],
        ],
    ]);

    $response->assertSessionHasErrors('items');
});

test('auditors can access purchase and sales screens but not mutate records', function () {
    $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);

    $this->actingAs($auditor)->get('/purchases')->assertOk();
    $this->actingAs($auditor)->get('/sales')->assertOk();

    $this->actingAs($auditor)->post('/purchases', [
        'supplier_name' => 'Blocked',
        'purchase_date' => '2026-04-22',
        'warehouse_id' => '00000000-0000-0000-0000-000000000000',
        'items' => [],
    ])->assertForbidden();

    $this->actingAs($auditor)->post('/sales', [
        'customer_name' => 'Blocked',
        'sale_date' => '2026-04-22',
        'warehouse_id' => '00000000-0000-0000-0000-000000000000',
        'items' => [],
    ])->assertForbidden();
});

test('void sale creates reversal entries and restores stock layers', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = Warehouse::query()->create([
        'name' => 'Main Warehouse',
        'location' => 'HQ',
    ]);
    $variant = createVariant(['sku' => 'SAL-VOID-001']);

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Acme Supplies',
        'purchase_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 10,
                'unit_cost' => 5,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Retail Buyer',
        'sale_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 3,
                'selling_price' => 9,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    $this->actingAs($admin)
        ->post("/sales/{$sale->id}/void", [
            'reason' => 'Customer order cancelled',
            'void_date' => '2026-04-23',
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $sale->refresh();

    expect($sale->status)->toBe('VOIDED');
    expect(InventoryTransaction::query()->where('reference_type', 'VOID')->where('reference_id', $sale->id)->where('transaction_type', 'SALE_RETURN')->count())->toBe(1);
    expect(CogsEntry::query()->where('quantity', '<', 0)->count())->toBeGreaterThan(0);
    expect((float) InventoryCostLayer::query()->where('warehouse_id', $warehouse->id)->where('variant_id', $variant->id)->sum('remaining_qty'))->toBe(10.0);
});

test('partial sale return records reversal and updates net sale amount', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = Warehouse::query()->create([
        'name' => 'Main Warehouse',
        'location' => 'HQ',
    ]);
    $variant = createVariant(['sku' => 'SAL-RET-001']);

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Acme Supplies',
        'purchase_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 10,
                'unit_cost' => 5,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Retail Buyer',
        'sale_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 4,
                'selling_price' => 10,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    $this->actingAs($admin)
        ->post("/sales/{$sale->id}/returns", [
            'warehouse_id' => $warehouse->id,
            'return_date' => '2026-04-23',
            'items' => [
                [
                    'variant_id' => $variant->id,
                    'quantity' => 1,
                ],
            ],
        ])
        ->assertSessionHasNoErrors()
        ->assertRedirect();

    $sale->refresh();

    expect((float) $sale->total_amount)->toBe(30.0);
    expect(InventoryTransaction::query()->where('reference_type', 'RETURN')->where('reference_id', $sale->id)->where('transaction_type', 'SALE_RETURN')->count())->toBe(1);
    expect((float) InventoryCostLayer::query()->where('warehouse_id', $warehouse->id)->where('variant_id', $variant->id)->sum('remaining_qty'))->toBe(7.0);
});

test('void purchase is blocked when stock has already been consumed', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = Warehouse::query()->create([
        'name' => 'Main Warehouse',
        'location' => 'HQ',
    ]);
    $variant = createVariant(['sku' => 'PUR-VOID-001']);

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Acme Supplies',
        'purchase_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 10,
                'unit_cost' => 5,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Retail Buyer',
        'sale_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 8,
                'selling_price' => 9,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $purchase = Purchase::query()->firstOrFail();

    $this->actingAs($admin)
        ->post("/purchases/{$purchase->id}/void", [
            'reason' => 'Invalid source document',
        ])
        ->assertSessionHasErrors('items');
});

test('sale update only changes allowed metadata fields', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = Warehouse::query()->create([
        'name' => 'Main Warehouse',
        'location' => 'HQ',
    ]);
    $variant = createVariant(['sku' => 'SAL-UPD-001']);

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Acme Supplies',
        'purchase_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 10,
                'unit_cost' => 5,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Original Name',
        'sale_date' => '2026-04-22',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 2,
                'selling_price' => 10,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $sale = Sale::query()->firstOrFail();

    $this->actingAs($admin)->put("/sales/{$sale->id}", [
        'customer_name' => 'Updated Name',
        'notes' => 'Updated note',
        'total_amount' => 99999,
    ])->assertSessionHasNoErrors()->assertRedirect();

    $sale->refresh();

    expect($sale->customer_name)->toBe('Updated Name');
    expect($sale->notes)->toBe('Updated note');
    expect((float) $sale->total_amount)->toBe(20.0);
});
