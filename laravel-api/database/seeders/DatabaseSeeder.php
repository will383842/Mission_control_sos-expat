<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::firstOrCreate(
            ['email' => 'williams@sos-expat.com'],
            [
                'name'      => 'Williams',
                'password'  => Hash::make('Admin2025!'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );
    }
}
