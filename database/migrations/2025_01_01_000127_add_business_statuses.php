<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase B1: a business's status (section 15.6), apart from its
 * subscription's, and every change to it. Each change keeps its reason
 * (shown to the owner), internal notes, what reactivates it, the day it
 * takes effect - or, for a scheduled closure, the closing day - whether the
 * owner was told, and the admin.
 *
 * The starting admin roles made in Phase A2 also get the Businesses pages
 * of their areas, where they exist.
 */
return new class extends Migration
{
    /** slug => the Businesses routes it gains */
    private const GAINS = [
        'billing-administrator'                 => ['admin.businesses.index', 'admin.businesses.show'],
        'support-administrator'                 => ['admin.businesses.index', 'admin.businesses.show', 'admin.businesses.notify'],
        'compliance-and-security-administrator' => ['admin.businesses.index', 'admin.businesses.show', 'admin.businesses.status'],
        'read-only-administrator'               => ['admin.businesses.index', 'admin.businesses.show'],
    ];

    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('status', 20)->default('active')->after('package_id')->index();
        });

        Schema::create('workspace_status_changes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('status', 20);
            $table->string('previous_status', 20)->nullable();
            // Shown to the business's owner.
            $table->text('reason');
            $table->text('reactivation')->nullable();
            // For admins only.
            $table->text('notes')->nullable();
            $table->date('effective_on');
            $table->date('closes_on')->nullable();
            $table->boolean('owner_notified')->default(false);
            // The admin who made it; none when izSuite did, closing on the scheduled day.
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('admin_name')->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['workspace_id', 'id']);
        });

        foreach (self::GAINS as $slug => $routes) {
            $role = Role::where('slug', $slug)->first();

            $role?->update(['permissions' => array_values(array_unique([...($role->permissions ?? []), ...$routes]))]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_status_changes');

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropIndex(['status']);
            $table->dropColumn('status');
        });
    }
};
