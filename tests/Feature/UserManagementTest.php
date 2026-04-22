<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin can view user management page', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->get('/users');

    $response->assertOk();
});

test('non admin users cannot access user management page', function () {
    $salesUser = User::factory()->create(['role' => User::ROLE_SALES]);

    $response = $this->actingAs($salesUser)->get('/users');

    $response->assertForbidden();
});

test('admin can create user with system default password', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->post('/users', [
        'name' => 'Sales User',
        'email' => 'sales@example.com',
        'phone' => '+251911234567',
        'role' => User::ROLE_SALES,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $createdUser = User::query()->where('email', 'sales@example.com')->first();

    expect($createdUser)->not->toBeNull();
    expect($createdUser?->role)->toBe(User::ROLE_SALES);
    expect(Hash::check('System@Pass', $createdUser?->password ?? ''))->toBeTrue();
});

test('admin can update user information', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create(['role' => User::ROLE_SALES]);

    $response = $this->actingAs($admin)->put('/users/'.$user->id, [
        'name' => 'Updated User',
        'email' => 'updated@example.com',
        'phone' => '+251922334455',
        'role' => User::ROLE_AUDITOR,
    ]);

    $response->assertSessionHasNoErrors()->assertRedirect();

    $user->refresh();

    expect($user->name)->toBe('Updated User');
    expect($user->email)->toBe('updated@example.com');
    expect($user->phone)->toBe('+251922334455');
    expect($user->role)->toBe(User::ROLE_AUDITOR);
});

test('admin can delete another user', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);
    $user = User::factory()->create(['role' => User::ROLE_SALES]);

    $response = $this->actingAs($admin)->delete('/users/'.$user->id);

    $response->assertSessionHasNoErrors()->assertRedirect();

    expect($user->fresh())->toBeNull();
});

test('admin cannot delete own account from user management', function () {
    $admin = User::factory()->create(['role' => User::ROLE_ADMIN]);

    $response = $this->actingAs($admin)->from('/users')->delete('/users/'.$admin->id);

    $response->assertSessionHasErrors('user')->assertRedirect('/users');
    expect($admin->fresh())->not->toBeNull();
});
