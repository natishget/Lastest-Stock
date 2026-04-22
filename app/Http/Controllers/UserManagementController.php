<?php

namespace App\Http\Controllers;

use App\Http\Requests\User\StoreUserRequest;
use App\Http\Requests\User\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserManagementController extends Controller
{
    private const DEFAULT_PASSWORD = 'System@Pass';

    public function index(Request $request): Response
    {
        $search = trim((string) $request->string('search')->toString());
        $role = trim((string) $request->string('role')->toString());

        $users = User::query()
            ->when($search !== '', function ($query) use ($search): void {
                $query->where(function ($innerQuery) use ($search): void {
                    $innerQuery
                        ->where('name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->when($role !== '', function ($query) use ($role): void {
                $query->where('role', $role);
            })
            ->orderBy('name')
            ->paginate(10)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone,
                'role' => $user->role,
                'created_at' => $user->created_at,
            ]);

        return Inertia::render('users/index', [
            'users' => $users,
            'filters' => [
                'search' => $search,
                'role' => $role,
            ],
            'roles' => User::roles(),
            'defaultPassword' => self::DEFAULT_PASSWORD,
        ]);
    }

    public function store(StoreUserRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        User::query()->create([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'role' => $validated['role'],
            'password' => Hash::make(self::DEFAULT_PASSWORD),
        ]);

        return back()->with('success', 'User created successfully.');
    }

    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->update($request->validated());

        return back()->with('success', 'User updated successfully.');
    }

    public function destroy(Request $request, User $user): RedirectResponse
    {
        if ($request->user()->is($user)) {
            throw ValidationException::withMessages([
                'user' => 'You cannot delete your own account from this page.',
            ]);
        }

        if ($user->role === User::ROLE_ADMIN && User::query()->where('role', User::ROLE_ADMIN)->count() <= 1) {
            throw ValidationException::withMessages([
                'user' => 'At least one admin user must remain in the system.',
            ]);
        }

        $user->delete();

        return back()->with('success', 'User deleted successfully.');
    }
}
