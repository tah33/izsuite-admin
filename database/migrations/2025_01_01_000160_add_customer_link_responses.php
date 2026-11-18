<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 16 Phase G2 (section 16.22): what a customer does through a document's
 * secure link - accepting or declining a quotation, or commenting - kept
 * with when and where from; when a link stops working; and Settings >
 * Sales's days a new link lasts (none to start: it never expires) and
 * whether customers may comment.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('customer_responses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('document_link_id')->nullable()->constrained()->nullOnDelete();
            $table->morphs('linkable');
            // accepted, rejected or comment
            $table->string('kind', 20);
            // Who, as they gave it; the comment, or why they declined.
            $table->string('name', 100)->nullable();
            $table->text('body')->nullable();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('document_links', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('revoked_at');
        });

        Schema::table('sales_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('link_expiry_days')->nullable()->after('credit_counts_orders');
            $table->boolean('link_comments')->default(true)->after('link_expiry_days');
        });
    }

    public function down(): void
    {
        Schema::table('sales_settings', fn (Blueprint $table) => $table->dropColumn(['link_expiry_days', 'link_comments']));
        Schema::table('document_links', fn (Blueprint $table) => $table->dropColumn('expires_at'));
        Schema::dropIfExists('customer_responses');
    }
};
