<?php

namespace App\Http\Requests\Api\Ecommerce;

use Illuminate\Foundation\Http\FormRequest;

/** A storefront's primary domain (Doc 19 Phase A3): one of its verified domains - none for its izSuite address. */
class ChoosePrimaryDomainRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'domain_id' => ['present', 'nullable', 'integer'],
        ];
    }
}
