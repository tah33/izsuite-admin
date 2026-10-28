<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How documents look and how they reach customers (Doc 06 sections 14 and 15,
 * Doc 11 sections 20 and 22, Doc 14 section 14.13):
 *
 * - document_templates: Settings > Documents & Templates, one per document
 *   type - Billing Starter has one active template for each. No row is the
 *   defaults. A template's signature or stamp image is a file of its own.
 * - template_snapshot on sales_documents and sales_payments: the template as
 *   it was when the document was numbered or the payment recorded, so later
 *   template changes never restyle what was already issued (Doc 11 section
 *   20). A draft - or a document from before - follows the current one.
 * - email_settings: Settings > Email - the sender name, reply-to address,
 *   language and the email templates, the manual payment reminder among
 *   them. No row is the defaults.
 * - document_emails: every document or reminder emailed - to whom, by whom,
 *   when, and whether it went (section 14.13: "Email delivery records the
 *   recipient, date, delivery status and sending user").
 * - document_links: secure links a customer opens a document with, without
 *   signing in. A link works until it is revoked or its document is voided or
 *   cancelled; its views are counted.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_templates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('document_type', 30);
            $table->char('color', 7);                                   // #1d4ed8
            $table->string('font', 20);                                 // sans | serif
            $table->string('paper_size', 10);                           // a4 | letter
            $table->string('header_layout', 20);                        // logo_left | logo_right | centered
            $table->boolean('show_logo')->default(true);
            $table->json('visible_fields');
            $table->json('line_columns');
            $table->boolean('show_notes')->default(true);
            $table->boolean('show_terms')->default(true);
            $table->boolean('show_payment_instructions')->default(true);
            $table->string('footer_text', 500)->nullable();
            $table->string('language', 10)->default('en');
            $table->string('date_format', 20);
            $table->string('number_format', 20);
            $table->timestamps();

            $table->unique(['workspace_id', 'document_type']);
        });

        Schema::table('sales_documents', function (Blueprint $t) {
            $t->json('template_snapshot')->nullable()->after('customer_snapshot');
        });

        Schema::table('sales_payments', function (Blueprint $t) {
            $t->json('template_snapshot')->nullable()->after('balance_after');
        });

        Schema::create('email_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->string('sender_name', 100)->nullable();             // the business's name when empty
            $table->string('reply_to')->nullable();                     // the owner's address when empty
            $table->string('language', 10)->default('en');
            $table->json('templates')->nullable();                      // {invoice: {subject, body}, ..., reminder: {...}}
            $table->timestamps();
        });

        Schema::create('document_emails', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->morphs('emailable');                                // the sales document or payment
            $table->string('kind', 20);                                 // document | reminder
            $table->string('to');
            $table->json('cc')->nullable();
            $table->string('subject');
            $table->string('status', 20)->default('queued');            // queued | sent | failed
            $table->string('failure', 500)->nullable();
            $table->foreignId('sent_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->timestamps();
        });

        Schema::create('document_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->morphs('linkable');
            $table->string('token', 64)->unique();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamp('last_viewed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('document_links');
        Schema::dropIfExists('document_emails');
        Schema::dropIfExists('email_settings');

        Schema::table('sales_payments', function (Blueprint $t) {
            $t->dropColumn('template_snapshot');
        });

        Schema::table('sales_documents', function (Blueprint $t) {
            $t->dropColumn('template_snapshot');
        });

        Schema::dropIfExists('document_templates');
    }
};
