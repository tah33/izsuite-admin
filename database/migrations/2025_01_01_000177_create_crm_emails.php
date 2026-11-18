<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase F3a: an e-mail sent from a lead, an opportunity or a contact
 * - to whom, its subject and message, what was attached (the record's sales
 * documents and files, by name), and whether the mail server took it - kept
 * on the record's timeline. Replies wait for an e-mail integration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('crm_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            // One of them: what it was sent from.
            $table->foreignId('lead_id')->nullable()->constrained('crm_leads')->cascadeOnDelete();
            $table->foreignId('opportunity_id')->nullable()->constrained('crm_opportunities')->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->cascadeOnDelete();
            $table->string('to');
            $table->json('cc')->nullable();
            $table->string('subject', 200);
            $table->text('body');
            $table->json('attachments')->nullable();                  // [{kind: document|file, id, name}]
            $table->string('status', 20)->default('queued');          // queued | sent | failed
            $table->string('failure', 500)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('crm_emails');
    }
};
