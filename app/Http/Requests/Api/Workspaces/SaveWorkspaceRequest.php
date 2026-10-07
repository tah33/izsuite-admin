<?php

namespace App\Http\Requests\Api\Workspaces;

use App\Models\Frontend\Application;
use App\Models\User\UserStaff;
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
     * form), and one of the caller's own staff who is active (GET /user-staff,
     * the ones with status 1). null takes the workspace off it; leaving the key
     * out leaves it as it was.
     *
     * user_id is not accepted at all - the workspace is always the caller's, so
     * one in the body is ignored. Nor can the staff member be someone else's:
     * another account's staff is not on offer to this one.
     *
     * Surrounding whitespace is trimmed before this runs, so a name of nothing
     * but spaces is empty here and fails `required`.
     */
    public function rules(): array
    {
        return [
            'name'     => ['required', 'string', 'max:255'],
            'app_id'   => ['nullable', 'bail', 'integer', Rule::exists(Application::class, 'id')->where('is_active', true)],

            // Checked through the same scope that defines "active staff", and
            // only among the caller's own rows.
            'staff_id' => [
                'nullable',
                'bail',
                'integer',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $available = UserStaff::active()
                        ->where('user_id', $this->user()->id)
                        ->whereKey($value)
                        ->exists();

                    if (! $available) {
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
