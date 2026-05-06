<?php

use App\Models\User;

function roleUser(string $role): User
{
    return User::factory()->create(['role' => $role]);
}

test('sales users can access sales, purchases, and warehouse screens but not admin-only modules', function () {
    $salesUser = roleUser(User::ROLE_SALES);

    $this->actingAs($salesUser)->get('/dashboard')->assertOk();
    $this->actingAs($salesUser)->get('/sales')->assertOk();
    $this->actingAs($salesUser)->get('/purchases')->assertOk();
    $this->actingAs($salesUser)->get('/warehouses')->assertOk();
    $this->actingAs($salesUser)->getJson('/api/warehouses')->assertOk();

    $this->actingAs($salesUser)->get('/products')->assertForbidden();
    $this->actingAs($salesUser)->get('/cogs')->assertForbidden();
    $this->actingAs($salesUser)->get('/users')->assertForbidden();

    $this->actingAs($salesUser)->post('/products', [
        'name' => 'Test Product',
        'base_unit' => 'Piece',
    ])->assertForbidden();
});

test('auditors can view all data modules but cannot mutate protected records', function () {
    $auditor = roleUser(User::ROLE_AUDITOR);

    $this->actingAs($auditor)->get('/dashboard')->assertOk();
    $this->actingAs($auditor)->get('/products')->assertOk();
    $this->actingAs($auditor)->get('/sales')->assertOk();
    $this->actingAs($auditor)->get('/purchases')->assertOk();
    $this->actingAs($auditor)->get('/warehouses')->assertOk();
    $this->actingAs($auditor)->get('/cogs')->assertOk();
    $this->actingAs($auditor)->get('/users')->assertForbidden();

    $this->actingAs($auditor)->post('/products', [
        'name' => 'Blocked Product',
        'base_unit' => 'Piece',
    ])->assertForbidden();

    $this->actingAs($auditor)->post('/sales', [
        'customer_name' => 'Blocked Sale',
        'sale_date' => '2026-05-06',
        'warehouse_id' => '00000000-0000-0000-0000-000000000000',
        'items' => [],
    ])->assertForbidden();

    $this->actingAs($auditor)->post('/purchases', [
        'supplier_name' => 'Blocked Purchase',
        'purchase_date' => '2026-05-06',
        'warehouse_id' => '00000000-0000-0000-0000-000000000000',
        'items' => [],
    ])->assertForbidden();

    $this->actingAs($auditor)->post('/api/warehouses', [
        'name' => 'Blocked Warehouse',
        'location' => 'Nowhere',
    ])->assertForbidden();
});
