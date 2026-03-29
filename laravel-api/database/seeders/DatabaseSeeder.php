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
            ['email' => env('ADMIN_EMAIL', 'admin@example.com')],
            [
                'name'      => 'Williams Jullin',
                'password'  => Hash::make(env('ADMIN_PASSWORD', 'ChangeMe123!')),
                'role'      => 'admin',
                'is_active' => true,
            ]
        );

        $this->call(ContactTypeSeeder::class);
        $this->call(EmailTemplateSeeder::class);
        $this->call(PromptTemplateSeeder::class);
        $this->call(GenerationPresetSeeder::class);
        $this->call(PublishingEndpointSeeder::class);
        $this->call(AffiliateProgramSeeder::class);
    }
}
