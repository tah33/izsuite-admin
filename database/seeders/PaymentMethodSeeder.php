<?php

namespace Database\Seeders;

use App\Models\Billing\PaymentMethod;
use Illuminate\Database\Seeder;

class PaymentMethodSeeder extends Seeder
{
    public function run(): void
    {
        // Online gateways (pre-seeded, admin configures keys)
        $gateways = [
            ['type' => 'online', 'name' => 'Stripe',    'slug' => 'stripe',    'description' => 'Accept credit/debit cards via Stripe.',      'sort_order' => 1],
            ['type' => 'online', 'name' => 'PayPal',    'slug' => 'paypal',    'description' => 'PayPal payments and checkout.',               'sort_order' => 2],
            ['type' => 'online', 'name' => 'Paddle',    'slug' => 'paddle',    'description' => 'Paddle payment processing and subscriptions.', 'sort_order' => 3],
            ['type' => 'online', 'name' => 'Paystack',  'slug' => 'paystack',  'description' => 'Paystack for African payment methods.',       'sort_order' => 4],
            ['type' => 'online', 'name' => 'Razorpay',  'slug' => 'razorpay',  'description' => 'Razorpay for Indian payment methods.',        'sort_order' => 5],
        ];

        foreach ($gateways as $data) {
            PaymentMethod::firstOrCreate(['slug' => $data['slug']], $data);
        }

        // One sample offline method
        PaymentMethod::firstOrCreate(
            ['slug' => 'bank-transfer'],
            [
                'type'         => 'offline',
                'name'         => 'Bank Transfer',
                'description'  => 'Direct bank wire transfer.',
                'instructions' => "Please transfer the amount to:\n\nBank: Example Bank\nAccount: 1234567890\nRouting: 011000015\n\nInclude your order ID as the reference.",
                'is_active'    => true,
                'sort_order'   => 1,
            ]
        );
    }
}
