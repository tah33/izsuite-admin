<?php

namespace App\Http\Requests\Api\Sales;

use App\Models\User\Location;
use App\Models\User\SalesReturn;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A customer return made or changed (Doc 16 section 16.17): how much of each
 * delivered order line comes back, the day, where, why, in what condition and
 * - when known - what is done about it. delivery_id, when made from one of
 * the order's deliveries.
 */
class SaveReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $workspace = $this->attributes->get('workspace');

        return [
            'delivery_id'      => ['nullable', 'integer'],
            'return_date'      => ['nullable', 'date_format:Y-m-d'],
            'location_id'      => ['nullable', 'integer', Rule::exists(Location::class, 'id')->where('workspace_id', $workspace?->id)],
            'reason'           => ['required', 'string', 'max:500'],
            'condition'        => ['nullable', Rule::in(SalesReturn::CONDITIONS)],
            'resolution'       => ['nullable', Rule::in(SalesReturn::RESOLUTIONS)],
            'notes'            => ['nullable', 'string', 'max:5000'],
            'lines'            => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.line_id'  => ['required', 'integer'],
            'lines.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'min:0', 'max:999999999.999'],
        ];
    }

    public function messages(): array
    {
        return [
            'reason.required' => 'Say why it comes back.',
        ];
    }
}