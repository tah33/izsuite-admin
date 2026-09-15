<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Columns the frontend registration form collects that the users table had no
 * home for: prefix, first/last name, username and the terms agreement.
 *
 * Everything is nullable even though the form marks first name, username and
 * the terms box as required. The table already holds rows that predate this
 * form, so a NOT NULL column without a default could not be added at all -
 * required-ness belongs to the register request's validation rules, which only
 * apply to new sign-ups.
 *
 * `name` is left exactly as it was. It is NOT NULL and read all over the admin
 * panel, API resources and activity log, so registration must keep filling it
 * (first + last) rather than treat it as replaced by the two new columns.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'prefix')) {
                // Mr / Mrs / Miss / Ms / Dr - a title, not free text.
                $table->string('prefix', 20)->nullable()->after('name');
            }

            if (! Schema::hasColumn('users', 'first_name')) {
                $table->string('first_name')->nullable()->after('prefix');
            }

            if (! Schema::hasColumn('users', 'last_name')) {
                $table->string('last_name')->nullable()->after('first_name');
            }

            if (! Schema::hasColumn('users', 'username')) {
                // Unique, but nullable: existing rows have none, and MySQL lets
                // a unique index hold any number of NULLs.
                $table->string('username')->nullable()->unique()->after('last_name');
            }

            if (! Schema::hasColumn('users', 'terms_accepted_at')) {
                // When they agreed, not merely that they did - a boolean cannot
                // tell you which version of the terms was in force at the time.
                $table->timestamp('terms_accepted_at')->nullable()->after('email_verified_at');
            }
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (Schema::hasColumn('users', 'username')) {
                // Drop the index before the column it is built on.
                $table->dropUnique('users_username_unique');
            }
        });

        Schema::table('users', function (Blueprint $table) {
            foreach (['terms_accepted_at', 'username', 'last_name', 'first_name', 'prefix'] as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }
};
