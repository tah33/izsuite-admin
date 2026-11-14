<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase F2 (section 15.20): the platform's e-mails, written in the
 * admin. Each e-mail keeps the words built into the code until an active
 * template is saved for it in a language - its subject, message and
 * button, with variables - and every save is a version kept.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'product-administrator'   => ['admin.email-templates.index', 'admin.email-templates.edit', 'admin.email-templates.preview', 'admin.email-templates.test', 'admin.email-templates.restore'],
        'support-administrator'   => ['admin.email-templates.index', 'admin.email-templates.preview'],
        'read-only-administrator' => ['admin.email-templates.index'],
    ];

    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            // The e-mail it writes (EmailTemplateKinds), and the language it is in.
            $table->string('key', 60);
            $table->string('language', 10);
            $table->string('subject', 200);
            $table->text('body');
            $table->string('button', 60)->nullable();
            // active: sent in place of the built-in words; draft: kept, not sent.
            $table->string('status', 10)->default('draft');
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['key', 'language']);
        });

        Schema::create('email_template_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('email_template_id')->constrained('email_templates')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('subject', 200);
            $table->text('body');
            $table->string('button', 60)->nullable();
            $table->string('status', 10);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('created_at')->nullable();
            $table->unique(['email_template_id', 'version']);
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
        Schema::dropIfExists('email_template_versions');
        Schema::dropIfExists('email_templates');
    }
};
