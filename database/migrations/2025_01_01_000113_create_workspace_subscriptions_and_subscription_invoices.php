<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 13 Phase C1: a business's subscription to a paid plan, and izSuite's
 * invoices and receipts for it, paid by bank transfer and confirmed in the
 * admin. They are izSuite's own billing - never the business's documents -
 * so they live apart from sales_documents, and are numbered by izSuite's own
 * counters (billing_sequences).
 *
 * The old `subscriptions` and `invoices` tables belong to an earlier product
 * and are left as they are.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspace_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('package_id')->constrained('packages');
            $table->string('cycle', 10);
            $table->string('status', 30);
            // A business's one trial: its first day stays recorded after it ends.
            $table->date('trial_starts_on')->nullable();
            $table->date('trial_ends_on')->nullable();
            // The paid period, both days included.
            $table->date('period_starts_on')->nullable();
            $table->date('period_ends_on')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->string('number', 30)->unique();
            $table->foreignId('package_id')->constrained('packages');
            $table->string('package_name', 100);
            $table->string('cycle', 10);
            $table->date('period_starts_on')->nullable();
            $table->date('period_ends_on')->nullable();
            $table->char('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->decimal('credit', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('status', 10);
            $table->date('issued_on');
            $table->date('due_on');
            $table->json('billed_to');
            $table->json('seller');
            $table->text('payment_instructions')->nullable();
            $table->string('transfer_reference', 100)->nullable();
            $table->string('receipt_number', 30)->nullable()->unique();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('confirmed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('voided_at')->nullable();
            $table->timestamps();

            $table->index(['workspace_id', 'status']);
            $table->index(['status', 'issued_on']);
        });

        Schema::create('billing_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('name', 30)->unique();
            $table->unsignedInteger('last_value')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_sequences');
        Schema::dropIfExists('subscription_invoices');
        Schema::dropIfExists('workspace_subscriptions');
    }
};
