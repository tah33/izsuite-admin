<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase G1 (section 15.24): privacy requests - a business's data
 * exported, an account closed, data corrected - each worked only once the
 * person asking is checked, with its status, its outcome and, for an export,
 * the file and when it expires. Compliance and Security Administrators carry
 * them out; Support Administrators log them and check who is asking.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'compliance-and-security-administrator' => [
            'admin.privacy-requests.index', 'admin.privacy-requests.create', 'admin.privacy-requests.show', 'admin.privacy-requests.verify',
            'admin.privacy-requests.complete', 'admin.privacy-requests.close', 'admin.privacy-requests.reject', 'admin.privacy-requests.export', 'admin.privacy-requests.download',
        ],
        'support-administrator'   => ['admin.privacy-requests.index', 'admin.privacy-requests.create', 'admin.privacy-requests.show', 'admin.privacy-requests.verify'],
        'read-only-administrator' => ['admin.privacy-requests.index'],
    ];

    public function up(): void
    {
        Schema::create('privacy_requests', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20);
            $table->string('status', 20)->default('received');
            // The account and the business it is about.
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('workspace_id')->nullable()->constrained('workspaces')->nullOnDelete();
            $table->string('requester_name', 150);
            $table->string('requester_email', 190);
            $table->string('received_via', 20);
            $table->date('received_on');
            $table->date('due_on');
            $table->text('details');
            // How the person asking was checked, by whom and when.
            $table->text('identity_check')->nullable();
            $table->foreignId('verified_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('verified_at')->nullable();
            // What was done, or why it was refused.
            $table->text('outcome')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('completed_at')->nullable();
            // An export's file, on the private disk, until it expires.
            $table->string('export_path')->nullable();
            $table->unsignedBigInteger('export_size')->nullable();
            $table->timestamp('export_ready_at')->nullable();
            $table->timestamp('export_expires_at')->nullable();
            $table->unsignedInteger('export_downloads')->default(0);
            $table->timestamp('export_downloaded_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['status', 'due_on']);
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
        Schema::dropIfExists('privacy_requests');
    }
};
