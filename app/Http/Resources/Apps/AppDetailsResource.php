<?php

namespace App\Http\Resources\Apps;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * An app's own page in the Apps Center (Doc 13 section 6): what it is, each
 * edition with its state, plan, description and main features, and what it
 * brings - sidebar pages, reports and permissions - with the apps it needs
 * and that need it.
 *
 * @property array $resource
 */
class AppDetailsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $app = $this->resource;

        return [
            'family_key'  => $app['family_key'],
            'name'        => $app['name'],
            'icon'        => $app['icon'],
            'description' => $app['description'],
            'category'    => $app['category'],
            'active'      => $app['active'] ? ['name' => $app['active']['name'], 'edition' => $app['active']['edition']] : null,
            'has_history' => $app['has_history'],
            'support_url' => $app['support_url'],
            'editions'    => array_map(fn (array $edition) => [
                'app_key'          => $edition['app_key'],
                'name'             => $edition['name'],
                'edition'          => $edition['edition'],
                'state'            => $edition['state'],
                'package'          => $edition['package'],
                'requires'         => $edition['requires'],
                'missing'          => $edition['missing'],
                'description'      => $edition['description'],
                'long_description' => $edition['long_description'],
                'features'         => $edition['features'],
                ...Arr::only($edition, ['replaces', 'falls_back_to', 'required_by']),
            ], $app['editions']),
            'pages'       => $app['pages'],
            'reports'     => $app['reports'],
            'permissions' => $app['permissions'],
            'needed_by'   => $app['needed_by'],
        ];
    }
}
