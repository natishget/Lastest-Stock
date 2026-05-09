<?php

use App\Models\CostingMethod;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;

function csvExportVariant(): ProductVariant
{
    $product = Product::query()->create([
        'name' => 'CSV Export Product',
        'base_unit' => 'Piece',
    ]);

    return ProductVariant::query()->create([
        'product_id' => $product->id,
        'color' => 'Blue',
        'origin' => 'LOCAL',
        'sku' => 'CSV-001',
        'thickness' => 1.0,
        'size' => 'M',
    ]);
}

function csvExportWarehouse(): Warehouse
{
    return Warehouse::query()->create([
        'name' => 'CSV Warehouse',
        'location' => 'Main',
    ]);
}

function csvActivateCostingMethod(string $methodName): void
{
    SystemSetting::query()->delete();

    $method = CostingMethod::query()->create([
        'name' => $methodName,
    ]);

    SystemSetting::query()->create([
        'active_costing_method' => $method->id,
    ]);
}

test('authorized users can download cogs as csv for the selected costing method', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = csvExportWarehouse();
    $variant = csvExportVariant();

    csvActivateCostingMethod('FIFO');

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'CSV Supplier',
        'purchase_date' => '2026-04-10',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 5,
                'unit_cost' => 2,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'CSV Customer',
        'sale_date' => '2026-04-12',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 2,
                'selling_price' => 9,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $response = $this->actingAs($admin)->get('/cogs/export-csv?start_date=2026-04-01&end_date=2026-04-30&costing_method=FIFO');

    $response->assertOk();
    $response->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $response->assertDownload('cogs-FIFO-2026-04-01-2026-04-30.csv');

    $content = $response->streamedContent();

    expect($content)->toContain('"Product Name",Color,Origin,"Quantity Sold",Revenue,COGS,"Gross Profit","Profit Margin %"');
    expect($content)->toContain('"CSV Export Product",Blue,LOCAL,2,18,4,14,0');
});

test('non privileged users cannot download cogs csv', function () {
    $salesUser = User::factory()->create(['role' => User::ROLE_SALES]);

    $this->actingAs($salesUser)->get('/cogs/export-csv?start_date=2026-04-01&end_date=2026-04-30&costing_method=FIFO')
        ->assertForbidden();
});
