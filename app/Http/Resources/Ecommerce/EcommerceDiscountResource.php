<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceDiscount;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A discount code as the Discounts page shows and its form edits it (Doc 19
 * Phase H1): its name and code, what it takes off and on what - the products
 * it is for - its minimum, days and limits, whether it is active, its uses
 * (its orders not cancelled) and where it stands today.
 *
 * @property EcommerceDiscount $resource
 */
class EcommerceDiscountResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $discount = $this->resource;

        return [
            'id'                 => $discount->id,
            'name'               => $discount->name,
            'code'               => $discount->code,
            'kind'               => $discount->kind,
            'value'              => $discount->value === null ? null : rtrim(rtrim((string) $discount->value, '0'), '.'),
            'applies_to'         => $discount->applies_to,
            'products'           => $discount->products->map(fn (CatalogProduct $product) => ['id' => $product->id, 'title' => $product->title])->values(),
            'minimum'            => $discount->minimum === null ? null : (string) $discount->minimum,
            'starts_on'          => $discount->starts_on?->toDateString(),
            'ends_on'            => $discount->ends_on?->toDateString(),
            'usage_limit'        => $discount->usage_limit,
            'per_customer_limit' => $discount->per_customer_limit,
            // Phase H2a: given without a code; its priority (1 first) and whether it goes with other discounts.
            'automatic'          => $discount->isAutomatic(),
            'priority'           => $discount->priority,
            'combines'           => $discount->combines,
            'is_active'          => $discount->is_active,
            'uses'               => (int) $discount->uses,
            'state'              => $discount->currentState,
        ];
    }
}
