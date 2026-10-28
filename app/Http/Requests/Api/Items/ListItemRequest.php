<?php

namespace App\Http\Requests\Api\Items;

use App\Models\User\Item;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The search and the filters of the Items list (Doc 04 section 4), and
     * for_combo: only the items a combo can be made of; for_sale: only those a
     * sales line can take - active and sold, combos too.
     */
    public function rules(): array
    {
        return [
            'search'      => ['nullable', 'string', 'max:255'],
            'type'        => ['nullable', Rule::in(Item::TYPES)],
            'usage'       => ['nullable', Rule::in(Item::USAGES)],
            'category_id' => ['nullable', 'integer'],
            'brand_id'    => ['nullable', 'integer'],
            'status'      => ['nullable', Rule::in(['active', 'inactive'])],
            'for_combo'   => ['nullable', 'boolean'],
            'for_sale'    => ['nullable', 'boolean'],
            'per_page'    => ['nullable', 'integer', 'in:10,15,25,50,100'],
            'page'        => ['nullable', 'integer', 'min:1'],
        ];
    }
}
