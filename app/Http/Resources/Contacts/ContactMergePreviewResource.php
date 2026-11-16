<?php

namespace App\Http\Resources\Contacts;

use App\Models\User\Contact;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * What merging one contact into another would do (Doc 17 Phase C3), before
 * it is done: the two contacts, each detail the kept one would gain (its
 * column and value), what becomes of the other's notes, and how many records
 * would move to the kept one and how many financial ones stay.
 */
class ContactMergePreviewResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $preview = $this->resource;
        $changes = [];

        foreach ($preview['changes'] as $field => $value) {
            $changes[] = ['field' => $field, 'value' => $value instanceof \BackedEnum ? $value->value : $value];
        }

        return [
            'kept'    => $this->contact($preview['kept']),
            'merged'  => $this->contact($preview['merged']),
            'changes' => $changes,
            'notes'   => $preview['notes'],
            'moving'  => $preview['moving'],
            'staying' => $preview['staying'],
        ];
    }

    private function contact(Contact $contact): array
    {
        return [
            'id'             => $contact->id,
            'contact_number' => $contact->contact_number,
            'name'           => $contact->name,
        ];
    }
}
