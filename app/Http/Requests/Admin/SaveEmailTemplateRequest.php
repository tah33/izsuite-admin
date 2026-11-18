<?php

namespace App\Http\Requests\Admin;

use App\Models\Admin\EmailTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A platform e-mail's words in one language (Doc 15 section 15.20 - Phase
 * F2): its subject, message - paragraphs split by a blank line, variables
 * such as {plan} - the button's words, and whether it is sent (active) or
 * kept as a draft.
 */
class SaveEmailTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'subject' => ['required', 'string', 'max:200'],
            'body'    => ['required', 'string', 'max:10000'],
            'button'  => ['nullable', 'string', 'max:60'],
            'status'  => ['required', Rule::in([EmailTemplate::ACTIVE, EmailTemplate::DRAFT])],
        ];
    }
}
