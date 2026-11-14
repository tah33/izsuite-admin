<?php

namespace App\Http\Requests\Api\Sales;

/**
 * A confirmed sales order changed (Doc 16 section 16.9): the same fields as
 * its form saves, all of them needed as when it was confirmed, and the reason
 * it is changed - "Controlled amendments are allowed with permission and a
 * recorded reason".
 */
class AmendSalesOrderRequest extends SaveSalesDocumentRequest
{
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'reason' => ['required', 'string', 'max:500'],
        ];
    }

    public function messages(): array
    {
        return [
            ...parent::messages(),
            'reason.required' => 'Say why the order is changed.',
        ];
    }
}
