<?php

namespace App\Http\Resources\Plans;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                      => $this->id,
            'name'                    => $this->name,
            'slug'                    => $this->slug,
            'description'             => $this->description,
            'plan_for'                => $this->plan_for,
            'billing_type'            => $this->billing_type,

            'monthly_price'           => (float) $this->monthly_price,
            'monthly_price_formatted' => format_price((float) $this->monthly_price),
            'yearly_price'            => (float) $this->yearly_price,
            'yearly_price_formatted'  => format_price((float) $this->yearly_price),

            'trial_days'              => (int) $this->trial_days,
            'features'                => $this->features ?? [],

            // A null limit means unlimited - that is the convention the admin
            // panel already renders. limits_label carries the ready-to-print
            // string so consumers do not have to re-derive it.
            'limits'                  => [
                'job_postings'  => $this->job_postings_limit,
                'ai_screenings' => $this->ai_screenings_limit,
                'team_members'  => $this->team_members_limit,
            ],
            'limits_label'            => [
                'job_postings'  => $this->limitLabel($this->job_postings_limit),
                'ai_screenings' => $this->limitLabel($this->ai_screenings_limit),
                'team_members'  => $this->limitLabel($this->team_members_limit),
            ],

            'is_featured'             => (bool) $this->is_featured,
            'is_active'               => (bool) $this->is_active,
            'sort_order'              => (int) $this->sort_order,
            'created_at'              => to_display_timezone_iso($this->created_at),
        ];
    }

    private function limitLabel(?int $limit): string
    {
        return $limit === null ? __('Unlimited') : (string) $limit;
    }
}
