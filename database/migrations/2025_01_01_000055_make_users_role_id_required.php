<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Every account must carry a role.
     *
     * A null role_id is an account no permission check can reason about:
     * isAdmin() reads false, hasPermission() has nothing to look at, and the
     * account silently behaves like the weakest possible user. Letting the
     * column be null meant a bad seed or a deleted role could produce one
     * without anything failing.
     */
    public function up(): void
    {
        $this->backfillMissingRoles();

        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable(false)->change();
        });

        Schema::table('users', function (Blueprint $table) {
            // Was nullOnDelete, which a NOT NULL column cannot honour. The admin
            // panel already refuses to delete a role that still has users, so
            // this only makes the database agree with the rule the app enforces.
            $table->foreign('role_id')->references('id')->on('roles')->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['role_id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->unsignedBigInteger('role_id')->nullable()->change();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('role_id')->references('id')->on('roles')->nullOnDelete();
        });
    }

    /**
     * Park any role-less account on the frontend role rather than fail the
     * deploy over it. If that role is not seeded there is nothing safe to
     * guess, so stop with a message that says what to do.
     */
    private function backfillMissingRoles(): void
    {
        if (! DB::table('users')->whereNull('role_id')->exists()) {
            return;
        }

        $fallbackRoleId = DB::table('roles')->where('slug', 'user')->value('id');

        if (! $fallbackRoleId) {
            throw new RuntimeException(
                'Some users have no role_id and the "user" role is not seeded. '
                .'Run RoleSeeder (or assign those users a role) before migrating.'
            );
        }

        DB::table('users')->whereNull('role_id')->update(['role_id' => $fallbackRoleId]);
    }
};
