<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase F3b: a business's e-mail templates for its CRM e-mails -
 * Settings → CRM - each a name, a subject and a message whose placeholders
 * are filled in for the record and the sender as it is chosen.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_email_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('subject', 200);
            $table->text('body');
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_email_templates');
    }
};
