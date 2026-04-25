<?php

use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\User;

test('admin can view product management page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get('/products');

    $response->assertOk();
});

test('admin can see product variants individually on product management page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $product = Product::query()->create([
        'name' => 'Display Fabric',
        'base_unit' => 'Meter',
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'color' => 'Blue',
        'origin' => 'LOCAL',
        'sku' => 'DISP-BLUE-001',
        'thickness' => 1.1,
        'size' => 'M',
    ]);

    ProductVariant::query()->create([
        'product_id' => $product->id,
        'color' => 'Red',
        'origin' => 'IMPORTED',
        'sku' => 'DISP-RED-001',
        'thickness' => 1.2,
        'size' => 'L',
    ]);

    $response = $this->actingAs($admin)->get('/products');

    $response->assertOk();
    $response->assertSee('DISP-BLUE-001');
    $response->assertSee('DISP-RED-001');
});

test('non admin users cannot access product management page', function () {
    $salesUser = User::factory()->create(['role' => User::ROLE_SALES]);

    $response = $this->actingAs($salesUser)->get('/products');

    $response->assertForbidden();
});

test('admin can create product', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->post('/products', [
        'name' => 'Cotton Towel',
        'base_unit' => 'Piece',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $product = Product::query()->where('name', 'Cotton Towel')->first();

    expect($product)->not->toBeNull();
    expect($product?->base_unit)->toBe('Piece');
});

test('admin can update product', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $product = Product::query()->create([
        'name' => 'Face Towel',
        'base_unit' => 'Piece',
    ]);

    $response = $this->actingAs($admin)->put('/products/'.$product->id, [
        'name' => 'Bath Towel',
        'base_unit' => 'Pack',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $product->refresh();

    expect($product->name)->toBe('Bath Towel');
    expect($product->base_unit)->toBe('Pack');
});

test('admin can delete product without variants', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $product = Product::query()->create([
        'name' => 'Disposable Cup',
        'base_unit' => 'Box',
    ]);

    $response = $this->actingAs($admin)->delete('/products/'.$product->id);

    $response->assertSessionHasNoErrors()->assertRedirect();

    expect($product->fresh())->toBeNull();
});

test('admin can add variant from product management', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $product = Product::query()->create([
        'name' => 'Kitchen Towel',
        'base_unit' => 'Piece',
    ]);

    $response = $this->actingAs($admin)->post('/products/variants', [
        'product_id' => $product->id,
        'origin' => 'LOCAL',
        'color' => 'Green',
        'sku' => 'KITCHEN-GREEN-001',
        'thickness' => 0.95,
        'size' => 'L',
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $variant = ProductVariant::query()->where('sku', 'KITCHEN-GREEN-001')->first();

    expect($variant)->not->toBeNull();
    expect($variant?->product_id)->toBe($product->id);
    expect($variant?->origin)->toBe('LOCAL');
    expect($variant?->color)->toBe('Green');
});

test('product search and filters work together', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $match = Product::query()->create([
        'name' => 'Royal Fabric',
        'base_unit' => 'Meter',
    ]);

    ProductVariant::query()->create([
        'product_id' => $match->id,
        'color' => 'Blue',
        'origin' => 'LOCAL',
        'sku' => 'ROYAL-BLUE-001',
        'thickness' => 1.25,
        'size' => 'XL',
    ]);

    Product::query()->create([
        'name' => 'Plain Fabric',
        'base_unit' => 'Meter',
    ]);

    $response = $this->actingAs($admin)->get('/products?search=Royal&origin=LOCAL&color=Blue');

    $response->assertOk();
    $response->assertSee('Royal Fabric');
});
