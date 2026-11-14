<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase F1 (section 15.21): platform announcements - a banner in
 * the workspace for all businesses, those on chosen plans, in chosen
 * countries, with chosen apps on or named, or in the admin panel for
 * izSuite's admins only - between its days, with a priority, a link, and
 * whether each person can dismiss it for themselves.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'product-administrator'   => ['admin.announcements.index', 'admin.announcements.create', 'admin.announcements.edit', 'admin.announcements.destroy'],
        'support-administrator'   => ['admin.announcements.index', 'admin.announcements.create', 'admin.announcements.edit', 'admin.announcements.destroy'],
        'read-only-administrator' => ['admin.announcements.index'],
    ];

    public function up(): void
    {
        Schema::create('announcements', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            $table->text('message');
            // all, packages, countries, apps, businesses or admins.
            $table->string('audience', 20)->default('all');
            // What the audience names: plans' keys, countries' codes, app families' keys or businesses' IDs.
            $table->json('targets')->nullable();
            $table->string('priority', 20)->default('info');
            $table->string('link_url', 500)->nullable();
            $table->string('link_label', 60)->nullable();
            $table->boolean('dismissible')->default(true);
            // Empty: from when it is saved; no end.
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['starts_at', 'ends_at']);
        });

        // Who has put one away, each for themselves.
        Schema::create('announcement_dismissals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('announcement_id')->constrained('announcements')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['announcement_id', 'user_id']);
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
        Schema::dropIfExists('announcement_dismissals');
        Schema::dropIfExists('announcements');
    }
};
