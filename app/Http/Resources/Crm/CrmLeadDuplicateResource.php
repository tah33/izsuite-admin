<?php

namespace App\Http\Resources\Crm;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One of a lead's possible duplicates (Doc 17 Phase C2): another lead (its
 * business, status and owner) or a contact (its number), what matched, and
 * the decision about it - ignored with a reason, linked, or none yet.
 */
class CrmLeadDuplicateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'type'       => $row['type'],
            'id'         => $row['id'],
            'name'       => $row['name'],
            'detail'     => $row['detail'],
            'status'     => $row['status'],
            'owner'      => $row['owner'],
            'matched_on' => $row['matched_on'],
            'decision'   => $row['decision'],
            'reason'     => $row['reason'],
            'decided_by' => $row['decided_by'],
            'decided_at' => $row['decided_at'],
        ];
    }
}
