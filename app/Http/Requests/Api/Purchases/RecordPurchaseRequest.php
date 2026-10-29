<?php

namespace App\Http\Requests\Api\Purchases;

use Illuminate\Foundation\Http\FormRequest;

class RecordPurchaseRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Recording a draft (Doc 09 section 10). A supplier invoice recorded
     * before (section 11) is recorded again only once `confirmed`; an exact
     * duplicate needs the `reason` it is recorded anyway, which it keeps.
     */
    public function rules(): array
    {
        return [
            'confirmed' => ['nullable', 'boolean'],
            'reason'    => ['nullable', 'string', 'max:500'],
        ];
    }
}
