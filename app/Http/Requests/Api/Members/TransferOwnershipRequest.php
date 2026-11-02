<?php

namespace App\Http\Requests\Api\Members;

use Illuminate\Foundation\Http\FormRequest;

class TransferOwnershipRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** The owner's own password, confirming the hand-over (Doc 12 section 9). */
    public function rules(): array
    {
        return [
            'password' => ['required', 'string'],
        ];
    }
}
