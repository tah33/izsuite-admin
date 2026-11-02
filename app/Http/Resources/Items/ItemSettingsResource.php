<?php

namespace App\Http\Resources\Items;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings > Items: whether the business uses Brand Management and warranty
 * fields, its own units of measure - and those items have, which stay - the
 * unit new items start with (null for the type's first), whether new items
 * get a SKU and its prefix, and the barcode format. Wraps
 * ItemSettingService::settings().
 */
class ItemSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'brands_enabled'   => $this->resource['brands_enabled'],
            'warranty_enabled' => $this->resource['warranty_enabled'],
            'default_unit'     => $this->resource['default_unit'],
            'units'            => $this->resource['units'],
            'units_in_use'     => $this->resource['units_in_use'],
            'sku_auto'         => $this->resource['sku_auto'],
            'sku_prefix'       => $this->resource['sku_prefix'],
            'barcode_format'   => $this->resource['barcode_format'],
        ];
    }
}
