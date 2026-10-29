<?php

namespace Tests\Concerns;

use App\Models\Billing\Package;
use App\Models\Frontend\AppCategory;
use App\Models\Frontend\Application;

/**
 * For tests of what Purchases Lite brings: the app in the catalog and on every
 * plan, as AppCatalogSeeder sets it up for real - so a business made after
 * offerPurchasesLite() starts with it turned on.
 */
trait OffersPurchasesLite
{
    protected function offerPurchasesLite(): void
    {
        $category = AppCategory::firstOrCreate(['name' => 'Sales & Commerce'], ['sort_order' => 1]);

        $app = Application::create([
            'app_key'     => 'purchases_lite',
            'name'        => 'Purchases Lite',
            'family_key'  => 'purchases',
            'edition'     => 'lite',
            'category_id' => $category->id,
            'sort_order'  => 20,
            'is_active'   => true,
            'status'      => 'available',
        ]);

        $app->packages()->attach(Package::pluck('id'));
    }
}
