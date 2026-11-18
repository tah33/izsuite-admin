<?php

namespace App\Http\Resources\Crm;

use App\Http\Resources\Concerns\HidesValues;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A sales document made from an opportunity (Doc 17 Phase F1), as its page
 * lists it: which it is, its status as shown, its date, and its total for
 * those who see Sales' values. Expects shown_status set
 * (OpportunityDocuments::list()).
 */
class CrmOpportunityDocumentResource extends JsonResource
{
    use HidesValues;

    public function toArray(Request $request): array
    {
        return $this->hideValues('sales', [
            'id'            => $this->id,
            'kind'          => $this->type->kind(),
            'number'        => $this->number,
            'revision'      => $this->revision,
            'is_deposit'    => (bool) $this->is_deposit,
            'status'        => $this->shown_status,
            'document_date' => $this->document_date,
            'total'         => $this->grand_total,
            'currency'      => $this->currency,
        ], ['total']);
    }
}
