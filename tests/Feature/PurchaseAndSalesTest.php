<?php

use App\Models\CogsEntry;
use App\Models\InventoryCostLayer;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Purchase;
use App\Models\PurchaseItem;
use App\Models\Sale;
use App\Models\SaleItem;
use App\Models\User;

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
    $variantOne = createVariant(['sku' => 'PUR-001']);
    $variantTwo = createVariant(['sku' => 'PUR-002', 'color' => 'Red']);

    $response = $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Acme Supplies',
        'invoice_number' => 'INV-1001',
        'purchase_date' => '2026-04-22',
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
    $variantOne = createVariant(['sku' => 'SAL-001']);
    $variantTwo = createVariant(['sku' => 'SAL-002', 'color' => 'Red']);

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Acme Supplies',
        'purchase_date' => '2026-04-22',
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
    expect(InventoryCostLayer::query()->where('variant_id', $variantOne->id)->sum('remaining_qty'))->toBe(7);
    expect(InventoryCostLayer::query()->where('variant_id', $variantTwo->id)->sum('remaining_qty'))->toBe(4);
});

test('sale is blocked when stock is insufficient', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $variant = createVariant(['sku' => 'SAL-003']);

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Acme Supplies',
        'purchase_date' => '2026-04-22',
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

test('auditors cannot access purchase and sales management', function () {
    $auditor = User::factory()->create(['role' => User::ROLE_AUDITOR]);

    $this->actingAs($auditor)->get('/purchases')->assertForbidden();
    $this->actingAs($auditor)->get('/sales')->assertForbidden();
});
