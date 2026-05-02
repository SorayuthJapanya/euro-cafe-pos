<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class AdminUserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        User::firstOrCreate(
            [
                'email' => 'admin@mail.com'
            ],
            [
                'name' => 'Administrator',
                'password' => Hash::make('admin@2026'),
                'role' => 'admin'
            ]
        );

        User::firstOrCreate(
            [
                'email' => 'staff@mail.com'
            ],
            [
                'name' => 'Staff01',
                'password' => Hash::make('staff@2026')
            ]
        );
    }
}
