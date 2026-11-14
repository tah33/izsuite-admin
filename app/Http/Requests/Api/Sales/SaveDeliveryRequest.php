<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\Location;
use App\Models\User\SalesDelivery;
use App\Support\Countries;
use Illuminate\Validation\Rule;

/**
 * A draft or ready delivery changed (Doc 16 section 16.10): its date and
 * location, its own address (none: the order's), carrier, tracking number,
 * vehicle or driver, recipient, notes, and how much of each order line it
 * takes.
 */
class SaveDeliveryRequest extends CreateDeliveryRequest
{
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            ...parent::rules(),
            'delivery_date'              => ['required', 'date_format:Y-m-d'],
            'location_id'                => ['nullable', 'integer', Rule::exists(Location::class, 'id')->where('workspace_id', $workspace?->id)],
            'shipping_address'           => ['nullable', 'array:'.implode(',', SalesDelivery::ADDRESS_PARTS)],
            'shipping_address.line1'     => ['nullable', 'string', 'max:255'],
            'shipping_address.line2'     => ['nullable', 'string', 'max:255'],
            'shipping_address.city'      => ['nullable', 'string', 'max:100'],
            'shipping_address.state'     => ['nullable', 'string', 'max:100'],
            'shipping_address.postal_code' => ['nullable', 'string', 'max:20'],
            'shipping_address.country'   => ['nullable', Rule::in(Countries::codes())],
            'carrier'                    => ['nullable', 'string', 'max:100'],
            'tracking_number'            => ['nullable', 'string', 'max:100'],
            'vehicle'                    => ['nullable', 'string', 'max:100'],
            'recipient_name'             => ['nullable', 'string', 'max:150'],
            'notes'                      => ['nullable', 'string', 'max:2000'],
        ];
    }
}
