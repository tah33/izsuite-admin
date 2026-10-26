<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

/**
 * Repairs `apps` on databases that were created before the editions feature.
 *
 * 2025_01_01_000019_create_apps_table was edited after it had already run on
 * deployed databases: family_key, edition, sort_order and
 * unique(family_key, edition) were added to its CREATE, and the status default
 * changed from `recommended` to `available`. Laravel records migrations by
 * filename, so a database that already recorded 000019 never picks any of it
 * up - a fresh install gets those columns, every existing database does not.
 *
 * 000062_extend_the_app_catalog then reads all three: it orders by sort_order
 * and derives app_key from family_key and edition. On a pre-edit database it
 * dies with "Unknown column 'sort_order'", and because docker/entrypoint.sh
 * runs `migrate --force` under `set -e`, the app container restart-loops.
 *
 * This runs before 000062 and is entirely conditional, so on a database built
 * after the edit it finds the work already done and records itself without
 * touching a thing.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('apps', 'family_key')) {
            // Added nullable first: strict mode rejects a NOT NULL string
            // column on a table that already has rows. Backfilled, then
            // tightened to match what 000019 would have produced.
            Schema::table('apps', function (Blueprint $table) {
                $table->string('family_key', 100)->nullable();
            });

            $this->backfillFamilyKeys();

            Schema::table('apps', function (Blueprint $table) {
                $table->string('family_key', 100)->nullable(false)->change();
            });
        }

        if (! Schema::hasColumn('apps', 'edition')) {
            Schema::table('apps', function (Blueprint $table) {
                $table->string('edition', 50)->nullable();
            });
        }

        if (! Schema::hasColumn('apps', 'sort_order')) {
            Schema::table('apps', function (Blueprint $table) {
                $table->unsignedInteger('sort_order')->default(0);
            });
        }

        if (! Schema::hasIndex('apps', ['family_key'])) {
            Schema::table('apps', function (Blueprint $table) {
                $table->index('family_key');
            });
        }

        // Safe even where family_key repeats: every edition is still NULL at
        // this point, and MySQL does not collide NULLs in a unique index.
        if (! Schema::hasIndex('apps', ['family_key', 'edition'], 'unique')) {
            Schema::table('apps', function (Blueprint $table) {
                $table->unique(['family_key', 'edition']);
            });
        }

        // The other half of the same drift: 000019 now defaults status to
        // `available`, pre-edit databases still default it to `recommended`.
        // Only the default differs, so this is a no-op where it already matches.
        Schema::table('apps', function (Blueprint $table) {
            $table->string('status')->default('available')->change();
        });
    }

    /**
     * Deliberately empty. This migration repairs drift rather than adding a
     * feature: on a database built after the 000019 edit the columns belong to
     * 000019, and dropping them here would damage a schema this migration
     * never touched.
     */
    public function down(): void
    {
        //
    }

    /**
     * Each pre-editions row is its own product, so each needs a distinct
     * family_key - 000062 treats rows sharing one as tiers of a single app and
     * would rename all but the highest.
     */
    private function backfillFamilyKeys(): void
    {
        $taken = [];

        foreach (DB::table('apps')->orderBy('id')->get(['id', 'name']) as $row) {
            $base = Str::substr(Str::slug((string) $row->name, '_'), 0, 90) ?: 'app';
            $key = isset($taken[$base]) ? $base.'_'.$row->id : $base;
            $taken[$key] = true;

            DB::table('apps')->where('id', $row->id)->update(['family_key' => $key]);
        }
    }
};
