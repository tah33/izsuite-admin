<?php

namespace Database\Seeders;

use App\Models\Frontend\Page;
use App\Models\User\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class PageSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role_id', 1)->first();

        $pages = [
            [
                'title'          => 'Terms of Service',
                'content'        => '<h2>Terms of Service</h2>
<p>Welcome to Resumist. By accessing or using our service, you agree to these Terms of Service.</p>
<h3>1. Acceptance of Terms</h3>
<p>By creating an account or using Resumist, you acknowledge that you have read, understood, and agree to be bound by these terms.</p>
<h3>2. Description of Service</h3>
<p>Resumist provides subscription tracking, spending analytics, and optimization tools. We help users discover, manage, and cancel unwanted subscriptions.</p>
<h3>3. User Accounts</h3>
<p>You are responsible for maintaining the confidentiality of your account credentials and for all activities under your account.</p>
<h3>4. Privacy</h3>
<p>Your use of Resumist is also governed by our Privacy Policy. Please review it to understand our data practices.</p>
<h3>5. Modifications</h3>
<p>We reserve the right to modify these terms at any time. We will notify users of significant changes via email or in-app notification.</p>',
                'status'         => 'published',
                'show_in_footer' => true,
                'sort_order'     => 1,
            ],
            [
                'title'          => 'Privacy Policy',
                'content'        => '<h2>Privacy Policy</h2>
<p>At Resumist, we take your privacy seriously. This policy explains how we collect, use, and protect your information.</p>
<h3>1. Information We Collect</h3>
<p>We collect information you provide directly, such as your name, email, and subscription data. We do not sell your personal information to third parties.</p>
<h3>2. How We Use Your Information</h3>
<p>We use your information to provide and improve our services, send notifications about renewals, and analyze spending patterns to offer savings recommendations.</p>
<h3>3. Data Security</h3>
<p>We implement industry-standard security measures to protect your data, including encryption in transit and at rest.</p>
<h3>4. Data Retention</h3>
<p>We retain your data for as long as your account is active. You may request deletion of your data at any time by contacting support.</p>
<h3>5. Cookies</h3>
<p>We use essential cookies to maintain your session and preferences. We do not use third-party tracking cookies.</p>',
                'status'         => 'published',
                'show_in_footer' => true,
                'sort_order'     => 2,
            ],
            [
                'title'          => 'Refund Policy',
                'content'        => '<h2>Refund Policy</h2>
<p>We want you to be completely satisfied with Resumist.</p>
<h3>Free Plan</h3>
<p>Our Free plan is always free and requires no payment information.</p>
<h3>Pro & Business Plans</h3>
<p>If you are not satisfied with your paid subscription, you may request a full refund within 14 days of your initial purchase. Contact our support team at support@resumist.test.</p>
<h3>Cancellation</h3>
<p>You may cancel your subscription at any time from your account settings. Your access will continue until the end of your current billing period.</p>',
                'status'         => 'published',
                'show_in_footer' => true,
                'sort_order'     => 3,
            ],
        ];

        foreach ($pages as $pageData) {
            Page::firstOrCreate(
                ['slug' => Str::slug($pageData['title'])],
                array_merge($pageData, [
                    'slug'       => Str::slug($pageData['title']),
                    'created_by' => $admin?->id,
                ])
            );
        }
    }
}
