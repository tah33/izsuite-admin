<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase E1 (section 15.12): feature flags - a feature the code asks
 * about by its key, on for its audience (chosen businesses, and businesses
 * in chosen countries and plans, a share of them at a time) between its
 * days, with the admin responsible and an emergency off switch.
 */
return new class extends Migration
{
    /** slug => the routes it gains (a create or edit route lets its form save too) */
    private const GAINS = [
        'product-administrator'   => ['admin.feature-flags.index', 'admin.feature-flags.create', 'admin.feature-flags.edit', 'admin.feature-flags.destroy', 'admin.feature-flags.switch'],
        'read-only-administrator' => ['admin.feature-flags.index'],
    ];

    public function up(): void
    {
        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            // What the code asks by: fixed once made.
            $table->string('key', 60)->unique();
            $table->string('name', 100);
            $table->text('description')->nullable();
            // The admin's switch: off overrides everything.
            $table->string('status', 5)->default('off');
            // The audience: businesses by ID always; else those in the countries and plans chosen (none: all), a share of them.
            $table->json('businesses')->nullable();
            $table->json('countries')->nullable();
            $table->json('packages')->nullable();
            $table->unsignedTinyInteger('percentage')->default(0);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
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
        Schema::dropIfExists('feature_flags');
    }
};
