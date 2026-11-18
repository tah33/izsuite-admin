<?php

namespace App\Http\Resources\Billing;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One plan as the Pricing page and a business's Subscription compare them
 * (Doc 13 sections 2 and 11): what it costs - a price not yet announced, or
 * on a cycle it is not offered on, is null - the cycles it is offered on,
 * its trial, its limits - null for none - its reporting and support levels
 * and the apps it includes. The Pricing page also gets the countries a plan
 * offered in some only is offered in (Doc 15 Phase E3a).
 *
 * @property array $resource
 */
class PackageOfferResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $package = $this->resource;

        return [
            'key'              => $package['key'],
            'name'             => $package['name'],
            'description'      => $package['description'],
            'free'             => $package['free'],
            'price_currency'   => $package['price_currency'],
            'price_monthly'    => $package['price_monthly'],
            'price_yearly'     => $package['price_yearly'],
            'cycles'           => $package['cycles'],
            'trial_days'       => $package['trial_days'],
            'removes_branding' => $package['removes_branding'],
            'reporting_level'  => $package['reporting_level'],
            'support_level'    => $package['support_level'],
            'storage_limit_mb' => $package['storage_limit_mb'],
            'business_limit'   => $package['business_limit'],
            'user_limit'       => $package['user_limit'],
            'location_limit'   => $package['location_limit'],
            'apps'             => $package['apps'],
            'coming_soon'      => $package['coming_soon'],
            ...(array_key_exists('offered_in', $package) ? ['offered_in' => $package['offered_in']] : []),
            ...(array_key_exists('current', $package) ? ['current' => $package['current']] : []),
        ];
    }
}
