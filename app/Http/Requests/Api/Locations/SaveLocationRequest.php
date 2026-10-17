<?php

namespace App\Http\Requests\Api\Locations;

use Illuminate\Foundation\Http\FormRequest;

class SaveLocationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same for adding and for editing, at the lengths the locations
     * columns hold.
     *
     * Only the name is required. The country and state are optional: null (or a
     * blank, which becomes null) leaves one empty, and leaving the key out of an
     * update leaves it as it was. A new account's "Main Location" starts with
     * neither, since registration asks for neither.
     *
     * Surrounding whitespace is trimmed before this runs, so a name of nothing
     * but spaces is empty here and fails `required`.
     */
    public function rules(): array
    {
        return [
            'name'    => ['required', 'string', 'max:255'],
            'country' => ['nullable', 'string', 'max:100'],
            'state'   => ['nullable', 'string', 'max:100'],
        ];
    }
}
