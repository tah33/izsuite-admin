<?php

namespace App\Http\Resources\Apps;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Arr;

/**
 * The Apps Center AppsCenter makes, as its page reads it: the business, then
 * every listed app family by category, with each edition's state.
 */
class AppsCenterResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $workspace = $this->resource['workspace'];

        return [
            'workspace'  => [
                'name'    => $workspace['name'],
                'package' => $workspace['package'] ? ['name' => $workspace['package']['name']] : null,
            ],
            'categories' => array_map(fn (array $category) => [
                'id'       => $category['id'],
                'name'     => $category['name'],
                'icon'     => $category['icon'],
                'families' => array_map(fn (array $family) => $this->family($family), $category['families']),
            ], $this->resource['categories']),
        ];
    }

    private function family(array $family): array
    {
        return [
            'family_key'  => $family['family_key'],
            'name'        => $family['name'],
            'icon'        => $family['icon'],
            'description' => $family['description'],
            'active'      => $family['active'] ? ['name' => $family['active']['name'], 'edition' => $family['active']['edition']] : null,
            'has_history' => $family['has_history'],
            'editions'    => array_map(fn (array $edition) => $this->edition($edition), $family['editions']),
        ];
    }

    /** replaces, falls_back_to and required_by come only with the states they explain. */
    private function edition(array $edition): array
    {
        return [
            'app_key'  => $edition['app_key'],
            'name'     => $edition['name'],
            'edition'  => $edition['edition'],
            'state'    => $edition['state'],
            'requires' => $edition['requires'],
            'missing'  => $edition['missing'],
            ...Arr::only($edition, ['replaces', 'falls_back_to', 'required_by']),
        ];
    }
}
