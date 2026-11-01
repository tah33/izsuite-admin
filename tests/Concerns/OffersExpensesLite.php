<?php

namespace Tests\Concerns;

use App\Models\Billing\Package;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;

/**
 * For tests of what Expenses Lite brings: the app in the catalog and on every
 * plan, as AppCatalogSeeder sets it up for real - so a business made after
 * offerExpensesLite() starts with it turned on.
 */
trait OffersExpensesLite
{
    protected function offerExpensesLite(): void
    {
        $category = AppCategory::firstOrCreate(['name' => 'Sales & Commerce'], ['sort_order' => 1]);

        $app = Application::create([
            'app_key'     => 'expenses_lite',
            'name'        => 'Expenses Lite',
            'family_key'  => 'expenses',
            'edition'     => 'lite',
            'category_id' => $category->id,
            'sort_order'  => 30,
            'is_active'   => true,
            'status'      => 'available',
        ]);

        $app->packages()->attach(Package::pluck('id'));
    }
}
