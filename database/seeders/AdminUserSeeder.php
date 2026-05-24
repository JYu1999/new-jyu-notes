<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL') ?: 'admin@example.com';
        $name = env('ADMIN_NAME') ?: 'Admin';
        $password = env('ADMIN_PASSWORD') ?: 'changeme';

        User::updateOrCreate(
            ['email' => $email],
            [
                'name' => $name,
                'password' => Hash::make($password),
                'role' => User::ROLE_ADMIN,
                'email_verified_at' => now(),
            ],
        );

        $this->command->info("Admin user seeded: {$email}");
    }
}
