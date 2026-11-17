<?php

namespace App\Http\Requests\Api\Crm;

use App\Services\Api\Crm\CrmTimelineService;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** A record's timeline (Doc 17 Phase E3): all of it, or only its activities, notes, changes, files or - a contact's - sales documents. */
class CrmTimelineRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'show' => ['nullable', Rule::in(CrmTimelineService::SHOWS)],
        ];
    }
}
