<?php

namespace Database\Seeders;

use App\Models\Frontend\Category;
use App\Models\Billing\Subscription;
use App\Models\User\User;
use Illuminate\Database\Seeder;

class SubscriptionSeeder extends Seeder
{
    public function run(): void
    {
        $user          = User::where('email', 'recruiter@resumist.test')->first();

        if (! $user) {
            return;
        }

        $streaming     = Category::where('slug', 'streaming')->first();
        $music         = Category::where('slug', 'music')->first();
        $saas          = Category::where('slug', 'saas')->first();
        $storage       = Category::where('slug', 'storage')->first();
        $fitness       = Category::where('slug', 'fitness')->first();
        $productivity  = Category::where('slug', 'productivity')->first();

        $subscriptions = [
            [
                'name'              => 'Netflix',
                'category_id'       => $streaming?->id,
                'amount'            => 15.99,
                'billing_cycle'     => 'monthly',
                'status'            => 'active',
                'usage_status'      => 'high',
                'next_renewal_date' => now()->addDays(13),
            ],
            [
                'name'              => 'Spotify Premium',
                'category_id'       => $music?->id,
                'amount'            => 9.99,
                'billing_cycle'     => 'monthly',
                'status'            => 'active',
                'usage_status'      => 'high',
                'next_renewal_date' => now()->addDays(7),
            ],
            [
                'name'              => 'Adobe Creative Cloud',
                'category_id'       => $saas?->id,
                'amount'            => 54.99,
                'billing_cycle'     => 'monthly',
                'status'            => 'active',
                'usage_status'      => 'low',
                'next_renewal_date' => now()->addDays(21),
            ],
            [
                'name'              => 'Figma Pro',
                'category_id'       => $saas?->id,
                'amount'            => 12.00,
                'billing_cycle'     => 'monthly',
                'status'            => 'active',
                'usage_status'      => 'medium',
                'next_renewal_date' => now()->addDays(3),
            ],
            [
                'name'              => 'Gym Membership',
                'category_id'       => $fitness?->id,
                'amount'            => 49.99,
                'billing_cycle'     => 'monthly',
                'status'            => 'active',
                'usage_status'      => 'unused',
                'next_renewal_date' => now()->addDays(18),
            ],
            [
                'name'              => 'LinkedIn Premium',
                'category_id'       => $productivity?->id,
                'amount'            => 29.99,
                'billing_cycle'     => 'monthly',
                'status'            => 'active',
                'usage_status'      => 'low',
                'next_renewal_date' => now()->addDays(25),
            ],
            [
                'name'              => 'Hulu',
                'category_id'       => $streaming?->id,
                'amount'            => 17.99,
                'billing_cycle'     => 'monthly',
                'status'            => 'active',
                'usage_status'      => 'unused',
                'next_renewal_date' => now()->addDays(10),
            ],
            [
                'name'              => 'Dropbox Plus',
                'category_id'       => $storage?->id,
                'amount'            => 11.99,
                'billing_cycle'     => 'monthly',
                'status'            => 'active',
                'usage_status'      => 'unused',
                'next_renewal_date' => now()->addDays(30),
            ],
            [
                'name'              => 'Notion Pro',
                'category_id'       => $productivity?->id,
                'amount'            => 8.00,
                'billing_cycle'     => 'monthly',
                'status'            => 'active',
                'usage_status'      => 'low',
                'next_renewal_date' => now()->addDays(15),
            ],
        ];

        foreach ($subscriptions as $sub) {
            Subscription::firstOrCreate(
                [
                    'user_id' => $user->id,
                    'name'    => $sub['name'],
                ],
                array_merge($sub, [
                    'user_id'    => $user->id,
                    'currency'   => 'USD',
                    'is_manual'  => true,
                    'start_date' => now()->subMonths(rand(2, 12)),
                ])
            );
        }
    }
}
