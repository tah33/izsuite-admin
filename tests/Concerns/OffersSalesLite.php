<?php

namespace Tests\Concerns;

use App\Models\Billing\Package;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;

/**
 * For tests of what Sales Lite brings: the app in the catalog and on every
 * plan, as AppCatalogSeeder sets it up for real - so a business made after
 * offerSalesLite() starts with it turned on.
 */
trait OffersSalesLite
{
    protected function offerSalesLite(): void
    {
        $category = AppCategory::create(['name' => 'Sales & Commerce', 'sort_order' => 1]);

        $app = Application::create([
            'app_key'     => 'sales_lite',
            'name'        => 'Sales Lite',
            'family_key'  => 'sales',
            'edition'     => 'lite',
            'category_id' => $category->id,
            'sort_order'  => 10,
            'is_active'   => true,
            'status'      => 'available',
        ]);

        $app->packages()->attach(Package::pluck('id'));
    }
}
