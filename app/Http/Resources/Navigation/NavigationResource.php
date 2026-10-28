<?php

namespace App\Http\Resources\Navigation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The business sidebar NavigationBuilder makes, as the dashboard reads it:
 * the business, which app families it has on and at what edition, and the
 * menu.
 */
class NavigationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $workspace = $this->resource['workspace'];

        return [
            'workspace' => $workspace ? [
                'id'             => $workspace['id'],
                'name'           => $workspace['name'],
                'package'        => $workspace['package'] ? ['name' => $workspace['package']['name']] : null,
                'base_currency'  => $workspace['base_currency'],
                'currencies'     => $workspace['currencies'],
                'tax_mode'        => $workspace['tax_mode'],
                'withholding_tax' => $workspace['withholding_tax'] ?? false,
                'brands_enabled'  => $workspace['brands_enabled'],
            ] : null,

            // By family. An object even when empty, so the client always gets a map.
            'apps'      => (object) array_map(fn (array $app) => [
                'edition'     => $app['edition'],
                'status'      => $app['status'],
                'has_history' => $app['has_history'],
            ], (array) $this->resource['apps']),

            'menu'      => [
                'core'   => $this->resource['menu']['core'],
                'apps'   => array_map(fn (array $entry) => [
                    'family'   => $entry['family'],
                    'label'    => $entry['label'],
                    'icon'     => $entry['icon'],
                    'children' => $entry['children'],
                ], $this->resource['menu']['apps']),
                'bottom' => $this->resource['menu']['bottom'],
            ],
        ];
    }
}
