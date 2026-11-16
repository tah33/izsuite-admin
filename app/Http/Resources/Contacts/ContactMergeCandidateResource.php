<?php

namespace App\Http\Resources\Contacts;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A contact the one being merged may be another copy of (Doc 03's check,
 * Doc 17 Phase C3): its number, names and what matched.
 */
class ContactMergeCandidateResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $row = $this->resource;

        return [
            'id'             => $row['id'],
            'contact_number' => $row['contact_number'],
            'name'           => $row['name'],
            'trading_name'   => $row['trading_name'],
            'matched_on'     => $row['matched_on'],
        ];
    }
}
