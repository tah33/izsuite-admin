<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Drops the single `name` column in favour of `first_name` / `last_name`, which
 * is what the registration form actually collects.
 *
 * Existing rows are split on the first space - "Recruiter Demo" becomes
 * first_name "Recruiter", last_name "Demo" - so nobody loses their name. A
 * single-word name ("Admin") keeps last_name null.
 *
 * `first_name` becomes NOT NULL once the backfill has run, taking over the role
 * `name` had: every user must have something to be displayed as.
 *
 * Display code is unaffected: User::name() is now an accessor composing the two
 * columns, so `$user->name` still reads the same everywhere. Only SQL that
 * named the column had to change.
 */
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasColumn('users', 'name')) {
            return;
        }

        $this->splitNamesIntoParts();

        Schema::table('users', function (Blueprint $table) {
            // Safe only after the backfill above has given every row a value.
            $table->string('first_name')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('name');
        });
    }

    public function down(): void
    {
        if (Schema::hasColumn('users', 'name')) {
            return;
        }

        Schema::table('users', function (Blueprint $table) {
            // Nullable to begin with - the rows have nothing in it yet.
            $table->string('name')->nullable()->after('id');
        });

        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                DB::table('users')->where('id', $user->id)->update([
                    'name' => trim($user->first_name.' '.$user->last_name),
                ]);
            }
        });

        Schema::table('users', function (Blueprint $table) {
            $table->string('name')->nullable(false)->change();
            $table->string('first_name')->nullable()->change();
        });
    }

    /**
     * Copy `name` into first_name/last_name, leaving any row that already has a
     * first_name alone - re-running must not overwrite real data.
     */
    private function splitNamesIntoParts(): void
    {
        DB::table('users')->orderBy('id')->chunkById(100, function ($users) {
            foreach ($users as $user) {
                if (filled($user->first_name)) {
                    continue;
                }

                // A user with no name at all still needs first_name filled, or
                // the NOT NULL change below fails. Fall back to the email local
                // part, which is the only other thing guaranteed to be there.
                $name           = trim((string) $user->name) ?: strtok((string) $user->email, '@');

                [$first, $last] = array_pad(explode(' ', $name, 2), 2, null);

                DB::table('users')->where('id', $user->id)->update([
                    'first_name' => $first,
                    'last_name'  => filled($user->last_name) ? $user->last_name : (filled($last) ? trim($last) : null),
                ]);
            }
        });
    }
};
