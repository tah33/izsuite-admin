<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    public function run(): void
    {
        $this->call([
            RoleSeeder::class,
            PlanSeeder::class,
            SettingSeeder::class,
            LanguageSeeder::class,
            CurrencySeeder::class,
            PaymentMethodSeeder::class,
            UserSeeder::class,
            StaffSeeder::class,
            AppCatalogSeeder::class,
            DemoAccountSeeder::class,
            PageSeeder::class,
            TicketSeeder::class,
            ActivityLogSeeder::class,
        ]);
    }
}
