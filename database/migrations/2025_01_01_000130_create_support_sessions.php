<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase B4: support sessions (section 15.22), in place of Login as.
 *
 * Each keeps, before it opens: the business, the support case it is for,
 * the reason, its access (read only, or edit), the admin, when it started
 * and when it ends, and - for edit access - how the customer agreed. Then
 * when it ended and why, and the sensitive areas it opened.
 *
 * The starting admin roles trade Login as for the sessions of their areas:
 * Support opens read-only ones and ends them; Compliance and Security
 * reviews them. Edit access is the Super Admin's until a role is given it.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'support-administrator'                 => [
            'admin.businesses.support-session', 'admin.support-sessions.index', 'admin.support-sessions.open', 'admin.support-sessions.end',
        ],
        'compliance-and-security-administrator' => ['admin.support-sessions.index'],
        'read-only-administrator'               => ['admin.support-sessions.index'],
    ];

    public function up(): void
    {
        Schema::create('support_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ticket_id')->nullable()->constrained()->nullOnDelete();
            // The admin; none once their account is gone, when admin_name still says who.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_name');
            $table->string('access', 20);
            $table->text('reason');
            $table->text('customer_authorization')->nullable();
            $table->timestamp('started_at');
            $table->timestamp('expires_at');
            $table->timestamp('ended_at')->nullable();
            // expired, ended by an admin, or ended in the app.
            $table->string('end_reason', 20)->nullable();
            $table->json('areas')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'id']);
            $table->index(['ended_at', 'expires_at']);
        });

        foreach (Role::all() as $role) {
            $permissions = array_values(array_diff($role->permissions ?? [], ['admin.clients.login-as']));
            $gains       = self::GAINS[$role->slug] ?? [];

            if ($permissions !== ($role->permissions ?? []) || $gains) {
                $role->update(['permissions' => array_values(array_unique([...$permissions, ...$gains]))]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('support_sessions');
    }
};
