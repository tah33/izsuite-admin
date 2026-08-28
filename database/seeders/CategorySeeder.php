<?php

namespace Database\Seeders;

use App\Models\Frontend\Category;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $categories = [
            ['name' => 'Streaming',    'icon' => 'tv',          'color' => '#8B5CF6'],
            ['name' => 'Music',        'icon' => 'music',       'color' => '#EC4899'],
            ['name' => 'SaaS',         'icon' => 'cloud',       'color' => '#3B82F6'],
            ['name' => 'Gaming',       'icon' => 'gamepad-2',   'color' => '#10B981'],
            ['name' => 'Productivity', 'icon' => 'briefcase',   'color' => '#F59E0B'],
            ['name' => 'Storage',      'icon' => 'hard-drive',  'color' => '#6366F1'],
            ['name' => 'News',         'icon' => 'newspaper',   'color' => '#14B8A6'],
            ['name' => 'Fitness',      'icon' => 'dumbbell',    'color' => '#EF4444'],
            ['name' => 'Education',    'icon' => 'graduation-cap', 'color' => '#F97316'],
            ['name' => 'Other',        'icon' => 'box',         'color' => '#6B7280'],
        ];

        foreach ($categories as $cat) {
            Category::firstOrCreate(
                ['slug' => Str::slug($cat['name'])],
                array_merge($cat, [
                    'slug'      => Str::slug($cat['name']),
                    'is_system' => true,
                ])
            );
        }
    }
}
