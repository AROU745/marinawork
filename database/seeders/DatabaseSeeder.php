<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        User::query()->updateOrCreate(
            ['email' => 'admin@angele.local'],
            [
                'name' => 'Administrateur',
                'password' => 'admin123',
                'role' => User::ROLE_ADMIN,
            ]
        );

        User::query()->updateOrCreate(
            ['email' => 'user@angele.local'],
            [
                'name' => 'Vendeur',
                'password' => 'user123',
                'role' => User::ROLE_USER,
            ]
        );
    }
}
