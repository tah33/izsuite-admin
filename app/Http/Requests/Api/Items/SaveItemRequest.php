<?php

namespace App\Http\Requests\Api\Items;

use App\Models\User\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same for adding and for editing. Only the type and name are
     * required. A SKU is unique within the business, not across businesses,
     * and an item being edited may keep its own.
     *
     * Prices are in the business's base currency, never negative, to the cent:
     * at most 13 digits before the point and 2 after, which is what the
     * columns hold.
     */
    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        $price = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'];

        return [
            'type'           => ['required', Rule::in(Item::TYPES)],
            'name'           => ['required', 'string', 'max:255'],
            'sku'            => ['nullable', 'string', 'max:64', Rule::unique(Item::class, 'sku')->where('workspace_id', $workspace?->id)->ignore($this->route('id'))],
            'unit'           => ['nullable', 'string', 'max:20'],
            'sale_price'     => $price,
            'purchase_price' => $price,
            'description'    => ['nullable', 'string', 'max:5000'],
            'is_active'      => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return [
            'sku' => 'SKU',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'    => 'Choose product or service.',
            'sku.unique' => 'Another item of this business already has that SKU.',
            'decimal'    => 'The :attribute can have at most two decimal places.',
        ];
    }
}
