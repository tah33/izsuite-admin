<?php

namespace App\Http\Requests\Api\Items;

use App\Enums\ItemLabel;
use Closure;
use Illuminate\Foundation\Http\FormRequest;

class SaveItemLabelRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A category or a brand - whichever list the address names - is a name,
     * used once within the business whatever its case: "Chairs" and "chairs"
     * are the same category. One being renamed may keep its own.
     */
    public function rules(): array
    {
        /** @var ItemLabel $label */
        $label     = $this->route('label');
        $workspace = $this->attributes->get('workspace');

        return [
            'name' => ['bail', 'required', 'string', 'max:100', function (string $attribute, string $value, Closure $fail) use ($label, $workspace) {
                $taken = $label->model()::where('workspace_id', $workspace?->id)
                    ->whereRaw('LOWER(name) = ?', [mb_strtolower($value)])
                    ->when($this->route('id'), fn ($query, $id) => $query->where('id', '!=', $id))
                    ->exists();

                if ($taken) {
                    $fail("This business already has the {$label->noun()} \"{$value}\".");
                }
            }],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Enter a name.',
        ];
    }
}
