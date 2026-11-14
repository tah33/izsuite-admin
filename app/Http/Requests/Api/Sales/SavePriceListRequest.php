<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\ItemBrand;
use App\Models\User\ItemCategory;
use App\Models\User\Location;
use App\Models\User\PriceListRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A price list made or changed (Doc 16 section 16.19): its name, currency,
 * status, days, customers and locations (all, or the ones chosen), priority -
 * and its rules: what each prices (an item, a category, a brand or all), from
 * what quantity, between which days, and how (a fixed price, a percentage off
 * the item's price, or a markup on its cost).
 */
class SavePriceListRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');
        $own       = fn (string $model) => Rule::exists($model, 'id')->where('workspace_id', $workspace?->id);

        return [
            'name'                     => ['required', 'string', 'max:100'],
            'currency'                 => ['required', 'string', Rule::in($workspace?->currencyCodes() ?? [])],
            'is_active'                => ['required', 'boolean'],
            'valid_from'               => ['nullable', 'date_format:Y-m-d'],
            'valid_to'                 => ['nullable', 'date_format:Y-m-d', 'after_or_equal:valid_from'],
            'all_customers'            => ['required', 'boolean'],
            'contact_ids'              => ['exclude_if:all_customers,true', 'required', 'array', 'min:1', 'max:500'],
            'contact_ids.*'            => ['integer', $own(Contact::class)],
            'all_locations'            => ['required', 'boolean'],
            'location_ids'             => ['exclude_if:all_locations,true', 'required', 'array', 'min:1', 'max:200'],
            'location_ids.*'           => ['integer', $own(Location::class)],
            'priority'                 => ['required', 'integer', 'min:0', 'max:999'],
            'notes'                    => ['nullable', 'string', 'max:2000'],
            'rules'                    => ['present', 'array', 'max:500'],
            'rules.*.target'           => ['required', Rule::in(PriceListRule::TARGETS)],
            'rules.*.item_id'          => ['required_if:rules.*.target,item', 'nullable', 'integer', $own(Item::class)],
            'rules.*.item_category_id' => ['required_if:rules.*.target,category', 'nullable', 'integer', $own(ItemCategory::class)],
            'rules.*.item_brand_id'    => ['required_if:rules.*.target,brand', 'nullable', 'integer', $own(ItemBrand::class)],
            'rules.*.min_quantity'     => ['nullable', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
            'rules.*.method'           => ['required', Rule::in(PriceListRule::METHODS)],
            'rules.*.value'            => ['required', 'numeric', 'decimal:0,4', 'min:0', 'max:9999999999.9999'],
            'rules.*.valid_from'       => ['nullable', 'date_format:Y-m-d'],
            'rules.*.valid_to'         => ['nullable', 'date_format:Y-m-d'],
        ];
    }

    public function attributes(): array
    {
        return [
            'contact_ids'              => 'customers',
            'location_ids'             => 'locations',
            'rules.*.item_id'          => 'item',
            'rules.*.item_category_id' => 'category',
            'rules.*.item_brand_id'    => 'brand',
            'rules.*.min_quantity'     => 'minimum quantity',
        ];
    }
}