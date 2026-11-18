<?php

namespace App\Http\Requests\Api\Sales;

use App\Enums\Edition;
use App\Services\User\Apps\WorkspaceApps;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ConvertSalesDocumentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * What it becomes - a proforma or an invoice (Doc 08 section 11), or with
     * the full Sales app a sales order (Doc 16 Phase B) - and, for one
     * converted before, that it is meant to be converted again.
     */
    public function rules(): array
    {
        return [
            'to'    => ['required', Rule::in(['proforma', 'sales_order', 'invoice'])],
            'again' => ['sometimes', 'boolean'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $workspace = $this->attributes->get('workspace');

                if ($this->input('to') === 'sales_order' && $workspace && ! WorkspaceApps::for($workspace)->has('sales', Edition::Full)) {
                    $validator->errors()->add('to', 'Sales orders come with the Sales app. Turn it on in the Apps Center first.');
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'to.*' => 'Choose whether it becomes a proforma invoice, a sales order or an invoice.',
        ];
    }
}
