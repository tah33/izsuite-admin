<?php

namespace App\Http\Resources\Apps;

use App\Http\Resources\Billing\PackageOfferResource;
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
            'packages'   => PackageOfferResource::collection($this->resource['packages']),
        ];
    }
}
