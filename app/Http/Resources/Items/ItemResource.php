<?php

namespace App\Http\Resources\Items;

use App\Models\User\ItemComponent;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    /**
     * Every field, flat, as the table holds it, with its category and brand by
     * name. Prices go out as strings with two decimals ("12.50") - never as
     * floats, which cannot hold every cent.
     *
     * Its image, when it has one, comes with its address and a thumbnail's,
     * for lists. A combo carries its components, and component_total: what
     * they add up to at today's sales prices, which is its price when it is
     * priced as their sum. Expects category, brand, image and
     * components.component loaded.
     */
    public function toArray(Request $request): array
    {
        $storage = app(FileStorage::class);

        return [
            'id'                    => $this->id,
            'item_number'           => $this->item_number,
            'type'                  => $this->type,
            'usage'                 => $this->usage,
            'name'                  => $this->name,
            'sku'                   => $this->sku,
            'barcode'               => $this->barcode,
            'unit'                  => $this->unit,
            'category_id'           => $this->category_id,
            'category'              => $this->category ? ['id' => $this->category->id, 'name' => $this->category->name] : null,
            'brand_id'              => $this->brand_id,
            'brand'                 => $this->brand ? ['id' => $this->brand->id, 'name' => $this->brand->name] : null,
            'image'                 => $this->image ? [
                'id'            => $this->image->id,
                'url'           => $storage->url($this->image),
                'thumbnail_url' => $storage->url($this->image, true),
            ] : null,
            'description'           => $this->description,
            'sale_price'            => $this->sale_price,
            'sales_description'     => $this->sales_description,
            'purchase_price'        => $this->purchase_price,
            'purchase_description'  => $this->purchase_description,
            'delivery_method'       => $this->delivery_method,
            'delivery_instructions' => $this->delivery_instructions,
            'warranty_available'    => $this->warranty_available,
            'warranty_period'       => $this->warranty_period,
            'warranty_unit'         => $this->warranty_unit,
            'warranty_start_rule'   => $this->warranty_start_rule,
            'warranty_terms'        => $this->warranty_terms,
            'combo_pricing'         => $this->combo_pricing,
            'combo_display'         => $this->combo_display,
            'components'            => $this->isCombo()
                ? $this->components->map(fn (ItemComponent $row) => [
                    'item_id'     => $row->component_id,
                    'item_number' => $row->component?->item_number,
                    'name'        => $row->component?->name,
                    'unit'        => $row->component?->unit,
                    'quantity'    => $row->quantity,
                    'sale_price'  => $row->component?->sale_price,
                    'is_active'   => $row->component?->is_active,
                ])->values()->all()
                : [],
            'component_total'       => $this->componentTotal(),
            'is_active'             => $this->is_active,
        ];
    }
}
