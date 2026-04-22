<?php

namespace Database\Seeders;

use App\Models\User;
// use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'natishget@gmail.com'],
            [
                'name' => 'Natnael Getachew',
                'phone' => '+251911000000',
                'email' => 'natishget@gmail.com',
                'password' => 'Nati@1234',
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ],
        );
    }
}
