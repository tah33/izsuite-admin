<?php

namespace Database\Seeders;

use App\Models\Frontend\ContentItem;
use App\Models\Admin\Setting;
use Illuminate\Database\Seeder;

class ContentSeeder extends Seeder
{
    public function run(): void
    {
        // ── Settings-based sections (Hero, CTA, Footer) ──
        $settings = [
            // Hero
            ['group' => 'frontend', 'key' => 'hero_badge_text',      'value' => '🚀 Save Money Automatically'],
            ['group' => 'frontend', 'key' => 'hero_title',           'value' => 'Stop Paying for Subscriptions'],
            ['group' => 'frontend', 'key' => 'hero_title_highlight', 'value' => "You Don't Use"],
            ['group' => 'frontend', 'key' => 'hero_description',     'value' => 'Resumist automatically detects forgotten, duplicate, and overpriced subscriptions — saving you hundreds of dollars every year.'],
            ['group' => 'frontend', 'key' => 'hero_primary_cta',     'value' => 'Start Saving Now'],
            ['group' => 'frontend', 'key' => 'hero_secondary_cta',   'value' => 'See How It Works'],
            ['group' => 'frontend', 'key' => 'hero_image',           'value' => '/images/hero-dashboard.jpg'],
            ['group' => 'frontend', 'key' => 'hero_savings_label',   'value' => 'Avg. Annual Savings'],
            ['group' => 'frontend', 'key' => 'hero_savings_amount',  'value' => '$2,400+'],
            ['group' => 'frontend', 'key' => 'hero_checkmarks',      'value' => "No credit card required\nCancel anytime\n2-minute setup"],

            // CTA
            ['group' => 'frontend', 'key' => 'cta_title',       'value' => 'Start Saving Money Today'],
            ['group' => 'frontend', 'key' => 'cta_description', 'value' => 'Join thousands of users who have already saved millions by finding and eliminating subscription leaks.'],
            ['group' => 'frontend', 'key' => 'cta_button_text', 'value' => 'Get Started Free'],
            ['group' => 'frontend', 'key' => 'cta_button_url',  'value' => '/register'],

            // Footer
            ['group' => 'frontend', 'key' => 'footer_description',     'value' => 'Find and eliminate wasted subscriptions. Save money effortlessly with smart detection and analytics.'],
            ['group' => 'frontend', 'key' => 'footer_copyright',       'value' => '© 2026 Resumist. All rights reserved.'],
        ];

        foreach ($settings as $data) {
            Setting::firstOrCreate(['key' => $data['key'], 'group' => $data['group']], $data);
        }

        // ── Items-based sections ──
        $items    = [
            // Stats
            ['section' => 'stats', 'sort_order' => 0, 'data' => ['value' => '$2.4M+', 'label' => 'Saved by Users']],
            ['section' => 'stats', 'sort_order' => 1, 'data' => ['value' => '50K+',   'label' => 'Active Users']],
            ['section' => 'stats', 'sort_order' => 2, 'data' => ['value' => '150K+',  'label' => 'Leaks Found']],
            ['section' => 'stats', 'sort_order' => 3, 'data' => ['value' => '4.9/5',  'label' => 'User Rating']],

            // Features
            ['section' => 'features', 'sort_order' => 0, 'data' => ['icon' => 'search',      'title' => 'Auto-Detection',       'description' => 'Automatically scans your transactions to detect recurring subscriptions you may have forgotten about.']],
            ['section' => 'features', 'sort_order' => 1, 'data' => ['icon' => 'bar-chart-3',  'title' => 'Smart Analytics',      'description' => 'Visualize your spending patterns with detailed charts and get insights on where your money goes.']],
            ['section' => 'features', 'sort_order' => 2, 'data' => ['icon' => 'bell',         'title' => 'Renewal Alerts',       'description' => 'Never miss a renewal again. Get notified before you\'re charged for subscriptions you don\'t need.']],
            ['section' => 'features', 'sort_order' => 3, 'data' => ['icon' => 'shield-check', 'title' => 'Cancel Assistant',     'description' => 'Step-by-step guided workflows to help you cancel unwanted subscriptions with confidence.']],
            ['section' => 'features', 'sort_order' => 4, 'data' => ['icon' => 'credit-card',  'title' => 'Price Tracking',       'description' => 'Track price changes across all your subscriptions and get alerted when costs increase unexpectedly.']],
            ['section' => 'features', 'sort_order' => 5, 'data' => ['icon' => 'users',        'title' => 'Family Sharing',       'description' => 'Detect duplicate subscriptions across family members and consolidate to save even more.']],

            // How It Works
            ['section' => 'how_it_works', 'sort_order' => 0, 'data' => ['title' => 'Connect Your Accounts',          'description' => 'Securely link your bank accounts or upload transaction statements. Your data stays encrypted and private.']],
            ['section' => 'how_it_works', 'sort_order' => 1, 'data' => ['title' => 'We Detect Your Subscriptions',   'description' => 'Our smart engine automatically identifies recurring charges and categorizes them for you.']],
            ['section' => 'how_it_works', 'sort_order' => 2, 'data' => ['title' => 'Review & Take Action',           'description' => 'See all your subscriptions in one dashboard. Cancel what you don\'t need with our guided assistant.']],

            // Testimonials
            ['section' => 'testimonials', 'sort_order' => 0, 'data' => ['name' => 'Sarah Johnson',  'role' => 'Small Business Owner',    'quote' => 'I was shocked to find out I was paying for 6 subscriptions I completely forgot about. Saved me over $200/month!', 'avatar' => '']],
            ['section' => 'testimonials', 'sort_order' => 1, 'data' => ['name' => 'Michael Chen',   'role' => 'Software Engineer',       'quote' => 'The auto-detection is incredibly accurate. It found subscriptions I didn\'t even know I had. Highly recommend!', 'avatar' => '']],
            ['section' => 'testimonials', 'sort_order' => 2, 'data' => ['name' => 'Emily Rodriguez', 'role' => 'Marketing Director',     'quote' => 'The cancel assistant walked me through cancelling 4 services in under 10 minutes. Such a time saver!', 'avatar' => '']],

            // Social Links
            ['section' => 'social_links', 'sort_order' => 0, 'data' => ['platform' => 'X (Twitter)', 'url' => 'https://x.com/resumist',                 'icon' => 'twitter']],
            ['section' => 'social_links', 'sort_order' => 1, 'data' => ['platform' => 'GitHub',      'url' => 'https://github.com/resumist',              'icon' => 'github']],
            ['section' => 'social_links', 'sort_order' => 2, 'data' => ['platform' => 'LinkedIn',    'url' => 'https://linkedin.com/company/resumist',    'icon' => 'linkedin']],

            // Footer Columns (each item = one column with nested links)
            ['section' => 'footer_columns', 'sort_order' => 0, 'data' => [
                'name'  => 'Product',
                'links' => [
                    ['title' => 'Features',   'url' => '/#features'],
                    ['title' => 'Pricing',    'url' => '/#pricing'],
                    ['title' => 'About Us',   'url' => '/about'],
                    ['title' => 'Contact Us', 'url' => '/contact'],
                ],
            ]],
            ['section' => 'footer_columns', 'sort_order' => 1, 'data' => [
                'name'  => 'Legal',
                'links' => [
                    ['title' => 'Privacy Policy',   'url' => '/page/privacy-policy'],
                    ['title' => 'Terms of Service',  'url' => '/page/terms-of-service'],
                    ['title' => 'Cookie Policy',     'url' => '/page/cookie-policy'],
                ],
            ]],
        ];

        foreach ($items as $data) {
            ContentItem::firstOrCreate(
                ['section' => $data['section'], 'sort_order' => $data['sort_order']],
                $data
            );
        }
    }
}
