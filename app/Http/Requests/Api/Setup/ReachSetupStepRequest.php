<?php

namespace App\Http\Requests\Api\Setup;

use App\Models\User\Workspace;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ReachSetupStepRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /** The First-Time Business Setup step the business moves on to (Doc 11 section 3). */
    public function rules(): array
    {
        return [
            'step' => ['required', 'string', Rule::in(array_keys(Workspace::SETUP_STEPS))],
        ];
    }

    public function messages(): array
    {
        return [
            'step.required' => 'Choose the setup step to move on to.',
            'step.in'       => 'Choose the setup step to move on to.',
        ];
    }
}
