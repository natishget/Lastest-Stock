<?php

use App\Models\CogsEntry;
use App\Models\CostingMethod;
use App\Models\InventoryCostLayer;
use App\Models\InventoryValuation;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Sale;
use App\Models\SystemSetting;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

function cogsTestVariant(array $attributes = []): ProductVariant
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

function cogsTestWarehouse(): Warehouse
{
    return Warehouse::query()->create([
        'name' => fake()->unique()->company(),
        'location' => fake()->address(),
    ]);
}

function activateCostingMethod(string $methodName): void
{
    DB::table('system_settings')->delete();

    $method = CostingMethod::query()->create([
        'name' => $methodName,
    ]);

    SystemSetting::query()->create([
        'active_costing_method' => $method->id,
    ]);
}

test('admin can view the cogs page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $this->actingAs($admin)->get('/cogs')->assertOk();
});

test('cogs page defaults to latest posted sale month when sales exist', function () {
    Carbon::setTestNow(Carbon::parse('2026-05-06'));

    try {
        $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

        Sale::query()->create([
            'customer_name' => 'Default Range Check',
            'total_amount' => 0,
            'sale_date' => '2026-04-27',
            'status' => Sale::STATUS_POSTED,
            'created_by' => $admin->id,
        ]);

        $this->actingAs($admin)
            ->get('/cogs')
            ->assertOk()
            ->assertSee('2026-04-01')
            ->assertSee('2026-04-30');
    } finally {
        Carbon::setTestNow();
    }
});

test('fifo costing stores layered cogs entries and report aggregates them', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = cogsTestWarehouse();
    $variant = cogsTestVariant(['sku' => 'FIFO-001', 'product_name' => 'Steel Sheet']);

    activateCostingMethod('FIFO');

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Alpha Supplies',
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

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Beta Supplies',
        'purchase_date' => '2026-04-11',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 5,
                'unit_cost' => 4,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Retail Buyer',
        'sale_date' => '2026-04-12',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 6,
                'selling_price' => 9,
            ],
        ],
    ])->assertSessionHasNoErrors();

    expect(CogsEntry::query()->count())->toBe(2);
    expect((float) CogsEntry::query()->sum('total_cost'))->toBe(14.0);
    expect((float) CogsEntry::query()->where('unit_cost', 2)->sum('total_cost'))->toBe(10.0);
    expect((float) CogsEntry::query()->where('unit_cost', 4)->sum('total_cost'))->toBe(4.0);
    expect(InventoryCostLayer::query()->whereNotNull('source_transaction_id')->count())->toBe(2);

    $response = $this->actingAs($admin)->getJson('/api/cogs?start_date=2026-04-01&end_date=2026-04-30&costing_method=FIFO');

    $response->assertOk();
    expect((float) $response->json('data.0.revenue'))->toBe(54.0);
    expect((float) $response->json('data.0.cogs'))->toBe(14.0);
    expect((float) $response->json('data.0.gross_profit'))->toBe(40.0);
});

test('alternate costing methods are recalculated when fifo is active', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = cogsTestWarehouse();
    $variant = cogsTestVariant(['sku' => 'ALT-001', 'product_name' => 'Galvanized Coil']);

    activateCostingMethod('FIFO');

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Alpha Supplies',
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

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Beta Supplies',
        'purchase_date' => '2026-04-11',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 5,
                'unit_cost' => 4,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Retail Buyer',
        'sale_date' => '2026-04-12',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 6,
                'selling_price' => 9,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $lifoResponse = $this->actingAs($admin)->getJson('/api/cogs?start_date=2026-04-01&end_date=2026-04-30&costing_method=LIFO');

    $lifoResponse->assertOk();
    expect((float) $lifoResponse->json('data.0.cogs'))->toBe(22.0);

    $weightedResponse = $this->actingAs($admin)->getJson('/api/cogs?start_date=2026-04-01&end_date=2026-04-30&costing_method=WEIGHTED_AVERAGE');

    $weightedResponse->assertOk();
    expect((float) $weightedResponse->json('data.0.cogs'))->toBe(18.0);
});

test('lifo costing consumes the newest cost layers first', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = cogsTestWarehouse();
    $variant = cogsTestVariant(['sku' => 'LIFO-001', 'product_name' => 'Aluminum Sheet']);

    activateCostingMethod('LIFO');

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Alpha Supplies',
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

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Beta Supplies',
        'purchase_date' => '2026-04-11',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 5,
                'unit_cost' => 4,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Retail Buyer',
        'sale_date' => '2026-04-12',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 6,
                'selling_price' => 9,
            ],
        ],
    ])->assertSessionHasNoErrors();

    expect(CogsEntry::query()->count())->toBe(2);
    expect((float) CogsEntry::query()->sum('total_cost'))->toBe(22.0);
    expect((float) CogsEntry::query()->where('unit_cost', 4)->sum('total_cost'))->toBe(20.0);
    expect((float) CogsEntry::query()->where('unit_cost', 2)->sum('total_cost'))->toBe(2.0);
});

test('weighted average costing stores a single cogs entry and updates valuation', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $warehouse = cogsTestWarehouse();
    $variant = cogsTestVariant(['sku' => 'WA-001', 'product_name' => 'Copper Coil']);

    activateCostingMethod('WEIGHTED_AVERAGE');

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Alpha Supplies',
        'purchase_date' => '2026-04-10',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 10,
                'unit_cost' => 4,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/purchases', [
        'supplier_name' => 'Beta Supplies',
        'purchase_date' => '2026-04-11',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 10,
                'unit_cost' => 6,
            ],
        ],
    ])->assertSessionHasNoErrors();

    $this->actingAs($admin)->post('/sales', [
        'customer_name' => 'Retail Buyer',
        'sale_date' => '2026-04-12',
        'warehouse_id' => $warehouse->id,
        'items' => [
            [
                'variant_id' => $variant->id,
                'quantity' => 4,
                'selling_price' => 9,
            ],
        ],
    ])->assertSessionHasNoErrors();

    expect(CogsEntry::query()->count())->toBe(1);
    expect(CogsEntry::query()->first()?->source_layer_id)->toBeNull();
    expect((float) CogsEntry::query()->sum('total_cost'))->toBe(20.0);

    $valuation = InventoryValuation::query()
        ->where('variant_id', $variant->id)
        ->where('warehouse_id', $warehouse->id)
        ->first();

    expect((float) $valuation?->total_quantity)->toBe(16.0);
    expect((float) $valuation?->total_cost)->toBe(80.0);
    expect((float) $valuation?->avg_unit_cost)->toBe(5.0);
});

test('example', function () {
    $response = $this->get('/');

    $response->assertStatus(200);
});
