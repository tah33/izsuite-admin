<?php

namespace Database\Seeders;

use App\Models\Admin\Language;
use Illuminate\Database\Seeder;

class LanguageSeeder extends Seeder
{
    public function run(): void
    {
        $languages = [
            ['name' => 'English',  'code' => 'en', 'native_name' => 'English',   'is_active' => true, 'is_default' => true,  'direction' => 'ltr'],
            ['name' => 'Spanish',  'code' => 'es', 'native_name' => 'Español',   'is_active' => true, 'is_default' => false, 'direction' => 'ltr'],
            ['name' => 'French',   'code' => 'fr', 'native_name' => 'Français',  'is_active' => true, 'is_default' => false, 'direction' => 'ltr'],
            ['name' => 'Arabic',   'code' => 'ar', 'native_name' => 'العربية',    'is_active' => true, 'is_default' => false, 'direction' => 'rtl'],
        ];

        foreach ($languages as $data) {
            Language::firstOrCreate(['code' => $data['code']], $data);
        }
    }
}
