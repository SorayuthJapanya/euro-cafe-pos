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
        User::updateOrCreate(
            [
                'email' => 'admin@mail.com'
            ],
            [
                'name' => 'Administrator',
                'password' => Hash::make('Admin@2026'),
                'role' => 'admin'
            ]
        );

        User::updateOrCreate(
            [
                'email' => 'staff@mail.com'
            ],
            [
                'name' => 'Staff01',
                'password' => Hash::make('Staff@2026')
            ]
        );
    }
}
