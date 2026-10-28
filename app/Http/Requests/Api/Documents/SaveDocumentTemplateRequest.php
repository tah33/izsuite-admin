<?php

namespace App\Http\Requests\Api\Documents;

use App\Models\User\DocumentTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SaveDocumentTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * How a kind of document looks (Doc 06 section 14), for a save and for a
     * preview of it alike: a colour as #RRGGBB, the font, paper and header
     * layout, which optional fields and line columns show, the footer text and
     * the language and formats. Fields a document must show cannot be hidden.
     */
    public function rules(): array
    {
        return [
            'color'                     => ['required', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'font'                      => ['required', Rule::in(DocumentTemplate::FONTS)],
            'paper_size'                => ['required', Rule::in(DocumentTemplate::PAPER_SIZES)],
            'header_layout'             => ['required', Rule::in(DocumentTemplate::HEADER_LAYOUTS)],
            'show_logo'                 => ['required', 'boolean'],
            'visible_fields'            => ['present', 'array'],
            'visible_fields.*'          => ['distinct', Rule::in(DocumentTemplate::VISIBLE_FIELDS)],
            'line_columns'              => ['present', 'array'],
            'line_columns.*'            => ['distinct', Rule::in(DocumentTemplate::LINE_COLUMNS)],
            'show_notes'                => ['required', 'boolean'],
            'show_terms'                => ['required', 'boolean'],
            'show_payment_instructions' => ['required', 'boolean'],
            'footer_text'               => ['nullable', 'string', 'max:300'],
            'language'                  => ['required', Rule::in(DocumentTemplate::LANGUAGES)],
            'date_format'               => ['required', Rule::in(DocumentTemplate::DATE_FORMATS)],
            'number_format'             => ['required', Rule::in(DocumentTemplate::NUMBER_FORMATS)],
        ];
    }

    public function attributes(): array
    {
        return [
            'paper_size'    => 'paper size',
            'header_layout' => 'header layout',
            'footer_text'   => 'footer text',
            'date_format'   => 'date format',
            'number_format' => 'number format',
        ];
    }

    public function messages(): array
    {
        return [
            'color.*'            => 'Choose a colour as #RRGGBB, such as #1D4ED8.',
            'visible_fields.*.*' => 'That field cannot be shown or hidden.',
            'line_columns.*.*'   => 'That column cannot be shown or hidden.',
        ];
    }
}
