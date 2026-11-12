<?php

namespace App\Http\Resources\Items;

use App\Services\Shared\EditConflicts;
use App\Http\Resources\Concerns\HidesValues;
use App\Models\User\ItemComponent;
use App\Models\User\TaxRate;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    use HidesValues;

    /** What a user without Items' View Purchase Prices or Costs does not see (Doc 12 section 14). */
    private const COSTS = ['purchase_price'];

    /**
     * Every field, flat, as the table holds it, with its category and brand by
     * name. Prices go out as strings with two decimals ("12.50") - never as
     * floats, which cannot hold every cent.
     *
     * Its image, when it has one, comes with its address and a thumbnail's,
     * for lists. Its default sales and purchase tax come with what the
     * profile shows of them. A combo carries its components, and
     * component_total: what they add up to at today's sales prices, which is
     * its price when it is priced as their sum. `in_use` says a sales
     * document or a purchase holds it, so it can only be deactivated, not
     * deleted (Doc 04 section 18), `in_sales` that a sales document does and
     * `in_purchases` that a purchase does; one just made is neither. Expects category, brand, salesTax,
     * purchaseTax, image and components.component loaded.
     */
    public function toArray(Request $request): array
    {
        $storage = app(FileStorage::class);

        return $this->hideWithout('items.costs', [
            'id'                    => $this->id,
            // When it was last changed: sent back as If-Match when it is saved (Doc 14 section 22).
            'version' => EditConflicts::version($this->resource),
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
            'sales_tax_id'          => $this->sales_tax_id,
            'sales_tax'             => $this->taxShown($this->salesTax),
            'sales_description'     => $this->sales_description,
            'purchase_price'        => $this->purchase_price,
            'purchase_tax_id'       => $this->purchase_tax_id,
            'purchase_tax'          => $this->taxShown($this->purchaseTax),
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

            // Who added it and who last changed it (Doc 14 section 14.5), where it was read with them
            'authors' => $this->whenLoaded('creator', fn () => [
                'added_by'   => $this->creator?->name,
                'added_at'   => $this->created_at?->toIso8601String(),
                'changed_by' => $this->updater?->name,
                'changed_at' => $this->updated_at?->toIso8601String(),
            ]),
            'in_use'                => (bool) ($this->in_sales ?? false) || (bool) ($this->in_purchases ?? false),
            'in_sales'              => (bool) ($this->in_sales ?? false),
            'in_purchases'          => (bool) ($this->in_purchases ?? false),
        ], self::COSTS);
    }

    /** A default tax as the profile names it: "VAT (15%)", "Exports (Zero-Rated)". */
    private function taxShown(?TaxRate $rate): ?array
    {
        return $rate ? [
            'name'      => $rate->name,
            'rate'      => $rate->rate,
            'treatment' => $rate->treatment->value,
            'is_active' => $rate->is_active,
        ] : null;
    }
}
