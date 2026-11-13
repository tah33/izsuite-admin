<?php

namespace App\Http\Resources\Navigation;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The business sidebar NavigationBuilder makes, as the dashboard reads it:
 * the business, which app families it has used - by name, whether on, and
 * at what edition - what the user may do in it, and the menu.
 */
class NavigationResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $workspace = $this->resource['workspace'];

        return [
            'workspace' => $workspace ? [
                'id'             => $workspace['id'],
                'business_id'    => $workspace['business_id'] ?? null,
                'name'           => $workspace['name'],
                // Shown beside the name in the header; null without one.
                'logo_url'       => $workspace['logo_url'] ?? null,
                'package'        => $workspace['package'] ? ['name' => $workspace['package']['name']] : null,
                'base_currency'  => $workspace['base_currency'],
                'currencies'     => $workspace['currencies'],
                'tax_mode'        => $workspace['tax_mode'],
                'withholding_tax' => $workspace['withholding_tax'] ?? false,
                'brands_enabled'  => $workspace['brands_enabled'],
                'warranty_enabled' => $workspace['warranty_enabled'] ?? true,
                'regional'        => $workspace['regional'] ?? null,
                'setup_completed' => $workspace['setup_completed'] ?? true,
                // Doc 15 section 15.6: null while Active; else its banner or why it cannot be opened.
                'status'          => $workspace['status'] ?? null,
            ] : null,

            // By family. An object even when empty, so the client always gets a
            // map. The name labels a family's documents - an app turned off too.
            'apps'      => (object) array_map(fn (array $app) => [
                'name'        => $app['name'],
                'edition'     => $app['edition'],
                'status'      => $app['status'],
                'has_history' => $app['has_history'],
            ], (array) $this->resource['apps']),

            // What the user may do in the business (Doc 12): "*" alone for full access.
            'permissions' => $this->resource['permissions'] ?? [],

            // Whether they may use every location (Doc 12 section 20).
            'all_locations' => $this->resource['all_locations'] ?? true,

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
