<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/** The POS selling screen's search (Doc 18 Phase C1): what was typed or scanned, and the category tile chosen. */
class SearchPosItemsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'search'      => ['nullable', 'string', 'max:100'],
            'category_id' => ['nullable', 'integer'],
        ];
    }
}
