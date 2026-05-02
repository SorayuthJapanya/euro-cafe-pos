<?php

namespace Database\Seeders;

use App\Models\Setting;
use Illuminate\Database\Seeder;

class SettingsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $defaults = [
            'shop_name' => 'Euro Cafe',
            'currency' => 'THB',
            'tax_rate' => '0',
            'receipt_footer' => 'Thank you for visiting Euro Cafe!',
            'promptpay_id' => ''
        ];

        foreach ($defaults as $key => $value) {
            Setting::firstOrCreate(
                ['key' => $key],
                ['value' => $value]
            );
        }
    }
}
