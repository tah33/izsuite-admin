<?php

namespace App\Http\Requests\Api\Pos;

use Illuminate\Foundation\Http\FormRequest;

/** A POS sale's receipt e-mailed (Doc 18 Phase C4): the one address it goes to, typed at the counter. */
class EmailPosReceiptRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'to' => ['required', 'email', 'max:255'],
        ];
    }

    public function attributes(): array
    {
        return [
            'to' => 'e-mail address',
        ];
    }
}
