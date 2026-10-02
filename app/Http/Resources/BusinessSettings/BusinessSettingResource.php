<?php

namespace App\Http\Resources\BusinessSettings;

use App\Services\Support\ImageService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class BusinessSettingResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        // logo is stored as a disk path; expose an absolute URL instead, the
        // same way UserResource does for the avatar, so the frontend never has
        // to know about the storage layout.
        $logoPath = app(ImageService::class)->publicUrl($this->logo);

        return [
            'business_name'              => $this->business_name,

            // Y-m-d, which is what an <input type="date"> takes as its value -
            // not the full timestamp the date cast would serialise to.
            'start_date'                 => $this->start_date?->toDateString(),

            'currency'                   => $this->currency,
            'language'                   => $this->language,
            'logo_url'                   => $logoPath ? url($logoPath) : null,
            'website'                    => $this->website,
            'business_contact_number'    => $this->business_contact_number,
            'alternate_contact_number'   => $this->alternate_contact_number,
            'country'                    => $this->country,
            'state'                      => $this->state,
            'city'                       => $this->city,
            'zip_code'                   => $this->zip_code,
            'landmark'                   => $this->landmark,
            'timezone'                   => $this->timezone,
            'tax_1_name'                 => $this->tax_1_name,
            'tax_1_no'                   => $this->tax_1_no,
            'tax_2_name'                 => $this->tax_2_name,
            'tax_2_no'                   => $this->tax_2_no,
            'financial_year_start_month' => $this->financial_year_start_month,
            'stock_accounting_method'    => $this->stock_accounting_method,
        ];
    }
}
