<?php

namespace App\Http\Resources\BusinessSettings;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings > Regional & Localization: the business's formats, week start,
 * paper and fiscal year start - and, for its country (a code, or null while
 * it has none), what that country recommends. Wraps
 * RegionalSettingService::show().
 */
class RegionalSettingsResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'date_format'             => $this->resource['date_format'],
            'time_format'             => $this->resource['time_format'],
            'number_format'           => $this->resource['number_format'],
            'week_start'              => $this->resource['week_start'],
            'paper_size'              => $this->resource['paper_size'],
            'fiscal_year_start_month' => $this->resource['fiscal_year_start_month'],
            'country'                 => $this->resource['country'],
            'recommended'             => $this->resource['recommended'],
        ];
    }
}
