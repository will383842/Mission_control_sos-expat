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
            ['email' => 'williamsjullin@gmail.com'],
            [
                'name'      => 'Williams',
                'password'  => Hash::make('MJMJsblanc19522008/*%$'),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );
    }
}
