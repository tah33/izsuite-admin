<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase D2 (section 15.18): each business's usage, one row a day -
 * its users, active locations, storage and active apps, with its plan and
 * that plan's limits then - for Admin > Usage's history.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'billing-administrator'   => ['admin.usage.index', 'admin.usage.show'],
        'support-administrator'   => ['admin.usage.index', 'admin.usage.show'],
        'read-only-administrator' => ['admin.usage.index', 'admin.usage.show'],
    ];

    public function up(): void
    {
        Schema::create('workspace_usage_snapshots', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            // The day it is of, in UTC.
            $table->date('day');
            $table->foreignId('package_id')->nullable()->constrained('packages')->nullOnDelete();
            $table->unsignedInteger('users');
            $table->unsignedInteger('locations');
            $table->unsignedBigInteger('storage_bytes');
            $table->unsignedInteger('apps');
            // The plan's limits that day: null for none.
            $table->unsignedInteger('user_limit')->nullable();
            $table->unsignedInteger('location_limit')->nullable();
            $table->unsignedInteger('storage_limit_mb')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'day']);
            $table->index('day');
        });

        foreach (Role::all() as $role) {
            $held        = $role->permissions ?? [];
            $permissions = array_values(array_unique([...$held, ...(self::GAINS[$role->slug] ?? [])]));

            if ($permissions !== $held) {
                $role->update(['permissions' => $permissions]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_usage_snapshots');
    }
};
