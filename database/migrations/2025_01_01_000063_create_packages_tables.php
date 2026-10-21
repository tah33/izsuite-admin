<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The plans a business can be on - Billing Starter, Standard, Premium (Doc 01
 * section 3) - and which apps each one gives access to.
 *
 * rank orders them. A higher plan includes everything a lower one does, so
 * package_apps lists an app under its lowest plan and every plan above it.
 * Access is not activation: the business turns apps on itself, in the Apps
 * Center (workspace_apps).
 *
 * Not the billing `plans` table, which prices recruiter subscriptions from an
 * earlier product. A billing plan can point at a package later, when checkout
 * for packages is built.
 *
 * The three rows are made here rather than by a seeder because the code relies
 * on them: every new business starts on Billing Starter. Names and descriptions
 * can be changed in the admin panel; keys and ranks cannot.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('packages', function (Blueprint $table) {
            $table->id();
            $table->string('key', 50)->unique();
            $table->string('name');
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('rank')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('package_apps', function (Blueprint $table) {
            $table->foreignId('package_id')->constrained()->cascadeOnDelete();
            $table->foreignId('app_id')->constrained('apps')->cascadeOnDelete();
            $table->primary(['package_id', 'app_id']);
        });

        $now = now();

        DB::table('packages')->insert([
            [
                'key'         => 'starter',
                'name'        => 'Billing Starter',
                'description' => 'Free forever. The core workspace with Sales Lite, Purchases Lite, Expenses Lite and basic reports.',
                'rank'        => 1,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'standard',
                'name'        => 'Standard',
                'description' => 'Everything in Billing Starter, plus the apps assigned to Standard and higher usage limits.',
                'rank'        => 2,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
            [
                'key'         => 'premium',
                'name'        => 'Premium',
                'description' => 'Everything in Standard, plus the apps assigned to Premium, advanced features and the highest limits.',
                'rank'        => 3,
                'is_active'   => true,
                'created_at'  => $now,
                'updated_at'  => $now,
            ],
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('package_apps');
        Schema::dropIfExists('packages');
    }
};
