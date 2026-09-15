<?php

namespace Database\Seeders;

use App\Models\Admin\Setting;
use Illuminate\Database\Seeder;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['group' => 'general', 'key' => 'site_name',        'value' => config('brand.name')],
            ['group' => 'general', 'key' => 'site_description', 'value' => config('brand.tagline')],
            ['group' => 'general', 'key' => 'site_url',         'value' => 'http://localhost'],
            ['group' => 'general', 'key' => 'timezone',         'value' => 'UTC'],
            ['group' => 'general', 'key' => 'support_email',    'value' => 'support@izsuite.test'],
            ['group' => 'general', 'key' => 'default_currency', 'value' => 'USD'],
            ['group' => 'general', 'key' => 'default_language', 'value' => 'en'],
            ['group' => 'currency', 'key' => 'symbol_position',      'value' => 'left'],
            ['group' => 'currency', 'key' => 'decimal_separator',    'value' => '.'],
            ['group' => 'currency', 'key' => 'thousand_separator',   'value' => ','],
            ['group' => 'currency', 'key' => 'decimals',             'value' => '2'],

            // Appearance
            ['group' => 'appearance', 'key' => 'logo_url',        'value' => null],
            ['group' => 'appearance', 'key' => 'favicon_url',     'value' => null],
            ['group' => 'appearance', 'key' => 'primary_color',   'value' => config('brand.colors.primary')],
            ['group' => 'appearance', 'key' => 'footer_text',     'value' => '(c) '.date('Y').' '.config('brand.name')],

            // Notifications
            ['group' => 'notifications', 'key' => 'renewal_reminder_days', 'value' => '7'],
            ['group' => 'notifications', 'key' => 'leak_alert_threshold',  'value' => '30'],
            ['group' => 'notifications', 'key' => 'email_notifications',   'value' => '1'],
        ];

        // Mail / SMTP. These belong to the admin, so re-running the seeder must
        // never wipe live credentials -- only fill in the keys that are missing.
        // Mirrors SettingService::DEFAULTS, which backfills the same keys.
        $mailSettings = [
            ['group' => 'mail', 'key' => 'smtp_enabled',      'value' => '0'],
            ['group' => 'mail', 'key' => 'smtp_host',         'value' => 'sandbox.smtp.mailtrap.io'],
            ['group' => 'mail', 'key' => 'smtp_port',         'value' => '2525'],
            ['group' => 'mail', 'key' => 'smtp_encryption',   'value' => 'tls'],
            ['group' => 'mail', 'key' => 'smtp_username',     'value' => 'b342f85d88af77'],
            ['group' => 'mail', 'key' => 'smtp_password',     'value' => 'de1849ecf20af7'],
            ['group' => 'mail', 'key' => 'smtp_from_address', 'value' => 'support@izsuite.test'],
            ['group' => 'mail', 'key' => 'smtp_from_name',    'value' => config('brand.name')],
        ];

        foreach ($settings as $data) {
            Setting::updateOrCreate(['key' => $data['key']], $data);
        }

        foreach ($mailSettings as $data) {
            Setting::firstOrCreate(['key' => $data['key']], $data);
        }

        // Settings are cached forever, so the seeded rows are invisible until
        // the cache is dropped.
        Setting::clearCache();
    }
}
