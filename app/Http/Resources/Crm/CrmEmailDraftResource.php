<?php

namespace App\Http\Resources\Crm;

use App\Models\User\SalesDocument;
use App\Models\User\WorkspaceFile;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What an e-mail from a lead, an opportunity or a contact starts with (Doc 17
 * Phase F3a): its address, its message, and what it can carry - the
 * record's sales documents, by name, and its files with their sizes.
 */
class CrmEmailDraftResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'to'        => $this->resource['to'],
            'body'      => $this->resource['body'],
            'documents' => $this->resource['documents']->map(fn (SalesDocument $document) => [
                'id'         => $document->id,
                'kind'       => $document->type->kind(),
                'number'     => $document->number,
                'revision'   => $document->revision,
                'is_deposit' => (bool) $document->is_deposit,
            ])->values()->all(),
            'files'     => $this->resource['files']->map(fn (WorkspaceFile $file) => ['id' => $file->id, 'name' => $file->name, 'size' => $file->size])->values()->all(),
        ];
    }
}
