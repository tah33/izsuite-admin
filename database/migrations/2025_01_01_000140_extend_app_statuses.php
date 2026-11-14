<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase E2 (section 15.10): an app's statuses beyond Available (now
 * shown as Published) and Coming Soon - Draft, Temporarily Unavailable,
 * Deprecated and Retired - the countries it is offered in, the app
 * families it conflicts with, and its release version. Apps not listed
 * become Drafts.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'product-administrator' => ['admin.apps.retire'],
    ];

    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            // Empty, every country: the business's country, from its profile.
            $table->json('countries')->nullable()->after('status');
            // App families it cannot be on beside, either way round.
            $table->json('conflicts')->nullable()->after('countries');
            $table->string('release_version', 20)->nullable()->after('conflicts');
        });

        DB::table('apps')->where('is_active', false)->update(['status' => 'draft']);

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
        DB::table('apps')->where('status', 'draft')->update(['status' => 'available', 'is_active' => false]);

        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn(['countries', 'conflicts', 'release_version']);
        });
    }
};
