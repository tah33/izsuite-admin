<?php

namespace App\Http\Requests\Api\Documents;

use App\Enums\DocumentType;
use App\Enums\SalesDocumentStatus;
use App\Repositories\User\DocumentIndexRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListDocumentsRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The Documents page's search and filters (Doc 06 section 4): by number,
     * reference, contact, item or amount; by source application - the app
     * family a document comes from - type, status - expired among them -
     * payment or credit status, overdue, contact, location, currency and a
     * range of dates.
     */
    public function rules(): array
    {
        $statuses = [...array_map(fn (SalesDocumentStatus $status) => $status->value, SalesDocumentStatus::cases()), SalesDocumentStatus::EXPIRED];

        return [
            'search'         => ['nullable', 'string', 'max:100'],
            'source'         => ['nullable', Rule::in(DocumentIndexRepository::SOURCES)],
            'type'           => ['nullable', Rule::in(array_map(fn (DocumentType $type) => $type->value, DocumentType::KINDS))],
            'status'         => ['nullable', Rule::in($statuses)],
            'payment_status' => ['nullable', Rule::in(['not_applicable', 'unpaid', 'partially_paid', 'paid', 'refunded', 'available', 'partially_applied', 'fully_applied'])],
            'overdue'        => ['nullable', 'boolean'],
            'contact_id'     => ['nullable', 'integer'],
            'location_id'    => ['nullable', 'integer'],
            'currency'       => ['nullable', 'string', 'size:3'],
            'date_from'      => ['nullable', 'date_format:Y-m-d'],
            'date_to'        => ['nullable', 'date_format:Y-m-d', 'after_or_equal:date_from'],
        ];
    }
}
