<?php

namespace App\Http\Resources\Documents;

use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * How one kind of document looks, as Settings > Documents edits it, with its
 * signature image - shown through a short-lived link, as it is not public.
 * Wraps DocumentTemplateService's ['template' => ..., 'signature' => ...].
 */
class DocumentTemplateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $template  = $this->resource['template'];
        $signature = $this->resource['signature'];

        return [
            'type'  => $template->document_type->value,
            'label' => $template->document_type->label(),
            ...$template->only([
                'color', 'font', 'paper_size', 'header_layout', 'show_logo', 'visible_fields', 'line_columns',
                'show_notes', 'show_terms', 'show_payment_instructions', 'footer_text', 'language', 'date_format', 'number_format',
            ]),
            'signature' => $signature ? [
                'name' => $signature->name,
                'url'  => app(FileStorage::class)->downloadLink($signature),
            ] : null,
        ];
    }
}
