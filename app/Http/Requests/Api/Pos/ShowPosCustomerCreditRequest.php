<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/** What credit a customer holds to pay a POS sale with (Doc 18 Phase D3b): the customer. */
class ShowPosCustomerCreditRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'contact_id' => ['required', 'integer'],
        ];
    }
}
