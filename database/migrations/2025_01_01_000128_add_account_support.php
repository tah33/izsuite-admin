<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase B2: the support actions on a user's account (section 15.7).
 *
 * A password reset ticket gets its own expiry: one bought with the e-mailed
 * code lasts 15 minutes, while the link izSuite support sends lasts 60, as it
 * has to wait in an inbox. A locked account is users.status "locked", which
 * needs no column.
 *
 * The starting admin roles made in Phase A2 get the account actions of their
 * areas, where they exist: Support recovers accounts, Compliance and
 * Security locks them.
 */
return new class extends Migration
{
    /** slug => the user routes it gains */
    private const GAINS = [
        'support-administrator'                 => [
            'admin.clients.verification', 'admin.clients.recovery', 'admin.clients.sign-out', 'admin.clients.two-factor',
        ],
        'compliance-and-security-administrator' => [
            'admin.clients.index', 'admin.clients.show', 'admin.clients.sign-out', 'admin.clients.lock', 'admin.clients.unlock',
        ],
    ];

    public function up(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('created_at');
        });

        foreach (self::GAINS as $slug => $routes) {
            $role = Role::where('slug', $slug)->first();

            $role?->update(['permissions' => array_values(array_unique([...($role->permissions ?? []), ...$routes]))]);
        }
    }

    public function down(): void
    {
        Schema::table('password_reset_tokens', function (Blueprint $table) {
            $table->dropColumn('expires_at');
        });
    }
};
