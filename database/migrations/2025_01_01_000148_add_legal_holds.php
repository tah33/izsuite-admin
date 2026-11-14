<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase G2 (section 15.24: "legal holds prevent affected records from
 * being permanently deleted"): a hold on a business while a legal matter
 * needs its records kept, with its reference and reason, until it is
 * released. Kept afterwards, with the business's name, as history.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'compliance-and-security-administrator' => ['admin.legal-holds.index', 'admin.legal-holds.create', 'admin.legal-holds.show', 'admin.legal-holds.release'],
        'read-only-administrator'               => ['admin.legal-holds.index'],
    ];

    public function up(): void
    {
        Schema::create('legal_holds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->string('business_name');
            $table->string('reference', 100)->nullable();
            $table->text('reason');
            $table->foreignId('placed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('placed_at');
            $table->foreignId('released_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->text('release_reason')->nullable();
            $table->timestamps();
            $table->index(['workspace_id', 'released_at']);
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
        Schema::dropIfExists('legal_holds');
    }
};
