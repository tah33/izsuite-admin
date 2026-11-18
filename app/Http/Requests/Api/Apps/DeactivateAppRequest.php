<?php

namespace App\Http\Requests\Api\Apps;

use Illuminate\Foundation\Http\FormRequest;

/** Turning an app off - with the apps that need it, when asked (Doc 13 section 10). */
class DeactivateAppRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'with_dependents' => ['sometimes', 'boolean'],
        ];
    }
}
