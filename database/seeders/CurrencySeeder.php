<?php

namespace Database\Seeders;

use App\Models\Admin\Currency;
use Illuminate\Database\Seeder;

class CurrencySeeder extends Seeder
{
    public function run(): void
    {
        $currencies = [
            ['name' => 'US Dollar',       'code' => 'USD', 'symbol' => '$',  'exchange_rate' => 1.000000, 'is_default' => true,  'is_active' => true],
            ['name' => 'Euro',            'code' => 'EUR', 'symbol' => '€',  'exchange_rate' => 0.920000, 'is_default' => false, 'is_active' => true],
            ['name' => 'British Pound',   'code' => 'GBP', 'symbol' => '£',  'exchange_rate' => 0.790000, 'is_default' => false, 'is_active' => true],
            ['name' => 'Bangladeshi Taka', 'code' => 'BDT', 'symbol' => '৳',  'exchange_rate' => 110.500000, 'is_default' => false, 'is_active' => true],
            ['name' => 'Indian Rupee',    'code' => 'INR', 'symbol' => '₹',  'exchange_rate' => 83.400000, 'is_default' => false, 'is_active' => true],
        ];

        foreach ($currencies as $currency) {
            Currency::updateOrCreate(
                ['code' => $currency['code']],
                $currency
            );
        }
    }
}
