<?php

namespace App\Http\Resources\Imports;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One row of an import, as the check and result screens list it: its line in
 * the file, enough to recognise it (its name, and an e-mail, phone, SKU or
 * barcode), what is wrong with it by field, the records or earlier rows it
 * might be a copy of, and what became of it.
 */
class ImportRowResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $data = $this->data ?? [];

        return [
            'line'       => $this->line,
            'name'       => $data['name'] ?? null,
            'detail'     => collect(['email', 'phone', 'sku', 'barcode'])->map(fn (string $key) => $data[$key] ?? null)->filter()->first(),
            'errors'     => (object) ($this->errors ?? []),
            'duplicates' => $this->duplicates ?? [],
            'include'    => $this->include,
            'outcome'    => $this->outcome,
            'record_id'  => $this->record_id,
            'message'    => $this->message,
        ];
    }
}
