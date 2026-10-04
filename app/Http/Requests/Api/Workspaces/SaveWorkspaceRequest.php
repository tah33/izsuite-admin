<?php

namespace App\Http\Requests\Api\Workspaces;

use App\Models\Frontend\Application;
use App\Models\User\User;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveWorkspaceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same for adding and for editing.
     *
     * The app and the staff member are both optional, and each must be one that
     * is on offer: an app an admin has left active (the list GET /apps hands the
     * form), a staff account that is active (GET /staff). null takes the
     * workspace off it; leaving the key out leaves it as it was.
     *
     * user_id is not accepted at all - the workspace is always the caller's, so
     * one in the body is ignored.
     *
     * Surrounding whitespace is trimmed before this runs, so a name of nothing
     * but spaces is empty here and fails `required`.
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'app_id'   => ['nullable', 'bail', 'integer', Rule::exists(Application::class, 'id')->where('is_active', true)],

            // Checked through the same scope that builds the list, not through a
            // copy of its conditions, so the two cannot drift apart.
            'staff_id' => [
                'nullable',
                'bail',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    if (! User::activeStaff()->whereKey($value)->exists()) {
                        $fail('Choose one of the available staff members.');
                    }
                },
            ],
        ];
    }

    public function attributes(): array
    {
        return [
            'app_id'   => 'app',
            'staff_id' => 'staff',
        ];
    }

    public function messages(): array
    {
        return [
            'app_id.exists' => 'Choose one of the available apps.',
        ];
    }
}
