<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase D3b (section 15.23): incidents - something wrong with a
 * service, from when it started until it is resolved - each with its
 * severity, status, the admin responsible and a timeline of notes, a note
 * able to move its status or who is responsible.
 */
return new class extends Migration
{
    /** slug => the routes it gains (a create or edit route lets its form save too) */
    private const GAINS = [
        'compliance-and-security-administrator' => ['admin.incidents.index', 'admin.incidents.show', 'admin.incidents.create', 'admin.incidents.edit', 'admin.incidents.note'],
        'support-administrator'                 => ['admin.incidents.index', 'admin.incidents.show', 'admin.incidents.note'],
        'billing-administrator'                 => ['admin.incidents.index', 'admin.incidents.show'],
        'read-only-administrator'               => ['admin.incidents.index', 'admin.incidents.show'],
    ];

    public function up(): void
    {
        Schema::create('incidents', function (Blueprint $table) {
            $table->id();
            $table->string('title', 150);
            // A Service Health service by its key - mail, payments... - or none.
            $table->string('service', 30)->nullable();
            $table->string('severity', 10);
            $table->string('status', 15);
            $table->text('summary')->nullable();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['status', 'started_at']);
        });

        Schema::create('incident_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('incident_id')->constrained('incidents')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('body');
            // What the note moved, as it was and became: the status, and who is responsible (by name, as they were then).
            $table->string('status_from', 15)->nullable();
            $table->string('status_to', 15)->nullable();
            $table->string('responsible_to', 120)->nullable();
            $table->timestamp('created_at')->useCurrent();
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
        Schema::dropIfExists('incident_notes');
        Schema::dropIfExists('incidents');
    }
};
