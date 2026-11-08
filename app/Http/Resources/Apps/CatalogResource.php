<?php

namespace App\Http\Resources\Apps;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public catalog (Doc 13 sections 2 and 4), as the website's Explore Apps
 * and Pricing pages read it: the apps by category, each edition with the plan
 * it needs, and the plans with their limits and apps.
 *
 * @property array{categories: list<array>, packages: list<array>} $resource
 */
class CatalogResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'categories' => array_map(fn (array $category) => [
                'id'   => $category['id'],
                'name' => $category['name'],
                'icon' => $category['icon'],
                'apps' => array_map(fn (array $app) => [
                    'family_key'  => $app['family_key'],
                    'name'        => $app['name'],
                    'icon'        => $app['icon'],
                    'description' => $app['description'],
                    'coming_soon' => $app['coming_soon'],
                    'editions'    => $app['editions'],
                ], $category['apps']),
            ], $this->resource['categories']),
            'packages'   => array_map(fn (array $package) => [
                'key'              => $package['key'],
                'name'             => $package['name'],
                'description'      => $package['description'],
                'storage_limit_mb' => $package['storage_limit_mb'],
                'business_limit'   => $package['business_limit'],
                'user_limit'       => $package['user_limit'],
                'location_limit'   => $package['location_limit'],
                'apps'             => $package['apps'],
                'coming_soon'      => $package['coming_soon'],
            ], $this->resource['packages']),
        ];
    }
}
