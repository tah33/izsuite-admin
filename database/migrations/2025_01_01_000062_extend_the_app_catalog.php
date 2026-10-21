<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The app catalog as the "izSuite Apps & Menus" plan describes it (Doc 01).
 *
 * app_categories gain an icon and an order: they are the Apps Center's groups.
 *
 * apps change in five ways:
 *   - category_id replaces the category name, so renaming a category no longer
 *     orphans its apps. A category that still has apps cannot be deleted - set
 *     it inactive instead.
 *   - app_key: a fixed, unique handle - sales_lite, sales - for URLs and code.
 *     Set once, never changed.
 *   - edition is lite or full and nothing else: Doc 01 defines just those two.
 *     A family has at most one of each, and an app with no Lite version is Full.
 *   - icon: the lucide icon the sidebar and the Apps Center show.
 *   - status describes the catalog only: available, or coming_soon. Whether an
 *     app is turned on for a business is in workspace_apps, not here.
 *
 * Existing rows are carried over: each category name becomes - or finds - a
 * category; every edition other than lite becomes full, and where that would
 * give one family two Full apps the extra ones move to a family of their own,
 * so no row is lost; the old per-business statuses become available.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_categories', function (Blueprint $table) {
            $table->string('icon', 100)->nullable()->after('name');
            $table->unsignedInteger('sort_order')->default(0)->after('icon');
        });

        Schema::table('apps', function (Blueprint $table) {
            $table->string('app_key', 100)->nullable()->after('id');
            $table->foreignId('category_id')->nullable()->after('logo_url')->constrained('app_categories')->restrictOnDelete();
            $table->string('icon', 100)->nullable()->after('category_id');
        });

        $this->carryOverRows();

        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn('category');
        });

        Schema::table('apps', function (Blueprint $table) {
            $table->string('app_key', 100)->nullable(false)->change();
            $table->string('edition', 10)->default('full')->nullable(false)->change();
            $table->unique('app_key');
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->string('category')->default('')->after('logo_url');
        });

        foreach (DB::table('app_categories')->get(['id', 'name']) as $category) {
            DB::table('apps')->where('category_id', $category->id)->update(['category' => $category->name]);
        }

        Schema::table('apps', function (Blueprint $table) {
            $table->dropUnique(['app_key']);
            $table->dropForeign(['category_id']);
            $table->dropColumn(['app_key', 'category_id', 'icon']);
            $table->string('edition', 50)->nullable()->default(null)->change();
        });

        Schema::table('app_categories', function (Blueprint $table) {
            $table->dropColumn(['icon', 'sort_order']);
        });
    }

    private function carryOverRows(): void
    {
        $now = now();

        foreach (DB::table('apps')->where('category', '!=', '')->distinct()->pluck('category') as $name) {
            $categoryId = DB::table('app_categories')->where('name', $name)->value('id')
                ?? DB::table('app_categories')->insertGetId([
                    'name' => $name, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
                ]);

            DB::table('apps')->where('category', $name)->update(['category_id' => $categoryId]);
        }

        // Highest tier first, so it is the one that stays Full.
        $families = DB::table('apps')->orderByDesc('sort_order')->orderBy('id')->get()->groupBy('family_key');

        foreach ($families as $family => $rows) {
            $fullTaken = false;

            foreach ($rows as $row) {
                if ($row->edition === 'lite') {
                    continue;
                }

                if (! $fullTaken) {
                    DB::table('apps')->where('id', $row->id)->update(['edition' => 'full']);
                    $fullTaken = true;

                    continue;
                }

                $ownFamily = $family.'-'.($row->edition ?: $row->id);

                if (DB::table('apps')->where('family_key', $ownFamily)->exists()) {
                    $ownFamily .= '-'.$row->id;
                }

                DB::table('apps')->where('id', $row->id)->update(['family_key' => $ownFamily, 'edition' => 'full']);
            }
        }

        foreach (DB::table('apps')->get(['id', 'family_key', 'edition']) as $row) {
            DB::table('apps')->where('id', $row->id)->update([
                'app_key' => $row->edition === 'lite' ? $row->family_key.'_lite' : $row->family_key,
            ]);
        }

        DB::table('apps')->where('status', '!=', 'coming_soon')->update(['status' => 'available']);
    }
};
