<?php

namespace Database\Seeders;

use App\Models\Billing\Plan;
use Illuminate\Database\Seeder;

class PlanSeeder extends Seeder
{
    public function run(): void
    {
        $plans = [
            [
                'name'                => 'Free',
                'slug'                => 'free',
                'plan_for'            => 'recruiter',
                'billing_type'        => 'monthly',
                'description'         => 'Free recruiter starter plan.',
                'monthly_price'       => 0,
                'yearly_price'        => 0,
                'trial_days'          => 0,
                'features'            => ['Basic recruiter workspace', 'Limited job postings', 'Limited AI screenings'],
                'job_postings_limit'  => 1,
                'ai_screenings_limit' => 10,
                'team_members_limit'  => 1,
                'is_active'           => true,
                'is_featured'         => false,
                'sort_order'          => 1,
            ],
            [
                'name'          => 'Pro',
                'slug'          => 'pro',
                'plan_for'      => 'recruiter',
                'billing_type'  => 'monthly',
                'description'   => 'Advanced tracking with smart leak detection.',
                'monthly_price' => 9.99,
                'yearly_price'  => 99.99,
                'trial_days'    => 14,
                'features'      => ['Unlimited subscriptions', 'Smart leak detection', 'Email & push alerts', 'CSV import', 'Priority support'],
                'is_active'     => true,
                'is_featured'   => true,
                'sort_order'    => 2,
            ],
            [
                'name'          => 'Business',
                'slug'          => 'business',
                'plan_for'      => 'recruiter',
                'billing_type'  => 'monthly',
                'description'   => 'Full platform for teams and businesses.',
                'monthly_price' => 29.99,
                'yearly_price'  => 299.99,
                'trial_days'    => 14,
                'features'      => ['Everything in Pro', 'Team management', 'API access', 'Custom reports', 'Dedicated support', 'SSO integration'],
                'is_active'     => true,
                'is_featured'   => false,
                'sort_order'    => 3,
            ],
        ];

        foreach ($plans as $data) {
            Plan::firstOrCreate(['slug' => $data['slug']], $data);
        }
    }
}
