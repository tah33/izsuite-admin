<?php

namespace App\Http\Requests\Api\Records;

use Illuminate\Foundation\Http\FormRequest;

class SaveCommentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * A comment on a sales document, purchase or expense (Doc 14 Phase D): its
     * words, plain text, and the users it mentions, by id. RecordCommentService
     * keeps only those who may view the record.
     */
    public function rules(): array
    {
        return [
            'body'       => ['required', 'string', 'max:2000'],
            'mentions'   => ['nullable', 'array', 'max:20'],
            'mentions.*' => ['integer', 'distinct'],
        ];
    }

    public function messages(): array
    {
        return [
            'body.required' => 'Write your comment first.',
            'body.max'      => 'A comment can be at most 2,000 characters.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('body'))) {
            $this->merge(['body' => trim($this->input('body'))]);
        }
    }
}
