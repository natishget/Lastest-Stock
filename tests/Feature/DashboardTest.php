<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;
use App\Models\Warehouse;

function dashboardTestVariant(array $attributes = []): ProductVariant
{
    $product = Product::query()->create([
        'name' => $attributes['product_name'] ?? 'Dashboard Product',
        'base_unit' => 'Piece',
    ]);

    return ProductVariant::query()->create([
        'product_id' => $product->id,
        'color' => $attributes['color'] ?? 'Blue',
        'origin' => $attributes['origin'] ?? 'IMPORTED',
        'sku' => $attributes['sku'] ?? 'DASH-001',
        'thickness' => $attributes['thickness'] ?? 1.0,
        'size' => $attributes['size'] ?? 'M',
    ]);
}

function dashboardTestWarehouse(): Warehouse
{
    return Warehouse::query()->create([
        'name' => 'Main',
        'location' => 'HQ',
    ]);
}

test('guests are redirected to the login page', function () {
    $this->get('/dashboard')->assertRedirect('/login');
});

test('authenticated users can visit the dashboard', function () {
    $this->actingAs($user = User::factory()->create());

    $this->get('/dashboard')->assertOk();
});

test('dashboard financials endpoint returns 12 fiscal months with cogs', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = dashboardTestWarehouse();
    $variant = dashboardTestVariant(['sku' => 'DASH-001', 'product_name' => 'Aluminum Panel']);

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Supplier One',
        'purchase_date' => '2025-07-01',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 5,
                'unit_cost' => 6500,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Supplier Two',
        'purchase_date' => '2025-07-03',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 8,
                'unit_cost' => 7000,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Buyer',
        'sale_date' => '2025-07-10',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 9,
                'selling_price' => 8000,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($admin)->getJson('/api/dashboard/financials?fiscal_year=2025');

    $response->assertOk();
    expect($response->json('months'))->toHaveCount(12);
    expect($response->json('months.0.month'))->toBe('2025-07');
    expect((float) $response->json('months.0.revenue'))->toBe(72000.0);
    expect((float) $response->json('months.0.cogs'))->toBe(60500.0);
    expect((float) $response->json('months.0.gross_profit'))->toBe(11500.0);
    expect((float) $response->json('totals.revenue'))->toBe(72000.0);
    expect((float) $response->json('totals.cogs'))->toBe(60500.0);
});

test('dashboard top products endpoint returns ranked variants', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = dashboardTestWarehouse();
    $variant = dashboardTestVariant(['sku' => 'DASH-TOP-001', 'product_name' => 'Steel Sheet', 'color' => 'Blue', 'origin' => 'IMPORTED']);

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Supplier One',
        'purchase_date' => '2025-07-01',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 5,
                'unit_cost' => 6500,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Buyer',
        'sale_date' => '2025-07-10',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 4,
                'selling_price' => 8000,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($admin)->getJson('/api/dashboard/top-products?fiscal_year=2025&limit=10');

    $response->assertOk();
    expect($response->json('data'))->toHaveCount(1);
    expect($response->json('data.0.label'))->toBe('Steel Sheet - Blue - Imported');
    expect((float) $response->json('data.0.total_quantity'))->toBe(4.0);
    expect((float) $response->json('data.0.revenue'))->toBe(32000.0);
});
