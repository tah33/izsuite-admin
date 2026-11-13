<?php

namespace App\Http\Requests\Admin;

use Illuminate\Foundation\Http\FormRequest;

/** A message from izSuite to a business (Doc 15 section 15.5): to its owner, or to everyone in it. */
class NotifyBusinessRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'title'    => ['required', 'string', 'max:150'],
            'message'  => ['required', 'string', 'max:2000'],
            'audience' => ['required', 'in:owner,everyone'],
        ];
    }
}
