<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase E3b (section 15.8): a plan's versions. A change to its
 * limits, branding, levels, trial or grace is a new version from a day,
 * kept with the ones before it. When one starts, the plan takes its terms
 * and a business subscribed to the plan stays on the version it was on
 * (workspaces.package_version_id); one without a subscription has its
 * plan's terms as they stand (null).
 */
return new class extends Migration
{
    /** The terms a version keeps, as the plan has them. */
    private const TERMS = ['storage_limit_mb', 'business_limit', 'user_limit', 'location_limit', 'removes_branding', 'reporting_level', 'support_level', 'trial_days', 'grace_days'];

    /** slug => the routes it gains */
    private const GAINS = [
        'billing-administrator' => ['admin.packages.version-remove'],
    ];

    public function up(): void
    {
        Schema::create('package_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('starts_on');
            // When it started: null while it waits for its day.
            $table->timestamp('applied_at')->nullable();
            $table->unsignedInteger('storage_limit_mb')->nullable();
            $table->unsignedInteger('business_limit')->nullable();
            $table->unsignedInteger('user_limit')->nullable();
            $table->unsignedInteger('location_limit')->nullable();
            $table->boolean('removes_branding')->default(false);
            $table->string('reporting_level', 20)->nullable();
            $table->string('support_level', 20)->nullable();
            $table->unsignedSmallInteger('trial_days')->nullable();
            $table->unsignedSmallInteger('grace_days')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['package_id', 'number']);
        });

        Schema::table('workspaces', function (Blueprint $table) {
            // The version of its plan it stays on; null, its plan's terms as they stand.
            $table->foreignId('package_version_id')->nullable()->after('package_id')->constrained('package_versions')->nullOnDelete();
        });

        // Each plan as it stands is its first version, from the day it was made.
        $now = now();

        foreach (DB::table('packages')->get() as $package) {
            DB::table('package_versions')->insert([
                'package_id' => $package->id,
                'number'     => 1,
                'starts_on'  => substr((string) ($package->created_at ?? $now->toDateString()), 0, 10),
                'applied_at' => $now,
                ...array_intersect_key((array) $package, array_flip(self::TERMS)),
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

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
        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropConstrainedForeignId('package_version_id');
        });

        Schema::dropIfExists('package_versions');
    }
};
