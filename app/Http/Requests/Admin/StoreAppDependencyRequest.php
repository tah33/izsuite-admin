<?php

namespace App\Http\Requests\Admin;

use App\Enums\Edition;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * One requirement for an app: a family, at least at min_edition. The rules that
 * need the app itself - not its own family, no loops, no plan above it - are
 * checked by the controller, which has it.
 */
class StoreAppDependencyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'requires_family' => ['required', 'string', Rule::exists('apps', 'family_key')],
            'min_edition'     => ['required', Rule::enum(Edition::class)],
        ];
    }

    public function attributes(): array
    {
        return [
            'requires_family' => 'required app',
            'min_edition'     => 'minimum edition',
        ];
    }
}
