<?php

namespace App\Http\Requests\Api\Billing;

use Illuminate\Foundation\Http\FormRequest;

/** The reference the business's bank transfer carries, so izSuite can match it to the invoice - empty to remove it. */
class SaveTransferReferenceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'transfer_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
