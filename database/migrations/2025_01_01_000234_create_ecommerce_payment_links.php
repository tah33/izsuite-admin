<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase F6a - payment links (section 19.23):
 *
 * - ecommerce_payment_links: a secure link for an online order not fully
 *   paid - the amount it asks, in the order's currency, the store's payment
 *   methods it offers, until when it works, to whom it was e-mailed and by
 *   whom - active, paid or revoked (and why: by hand, replaced by a newer
 *   link, the order cancelled, or its total changed). Its token is kept only
 *   hashed. A link is never a sale of its own: the order's invoice is;
 * - ecommerce_settings.payment_link_days: how long a new link works (7 to
 *   start);
 * - ecommerce_settings.partial_payments: whether a link may ask less than
 *   the order owes (off to start).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('ecommerce_payment_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ecommerce_order_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->decimal('amount', 15, 2);
            $table->string('currency', 3);
            $table->json('payment_methods');                                                // the store's methods it offers: [{id, name}], as sent
            $table->timestamp('expires_at');
            $table->string('status', 20)->default('active');                                // active | paid | revoked
            $table->string('sent_to', 255);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignId('revoked_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('revoked_why', 20)->nullable();                                  // revoked | replaced | cancelled | changed
            $table->timestamps();

            $table->index(['ecommerce_order_id', 'status']);
        });

        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('payment_link_days')->default(7)->after('hold_for_review');
            $table->boolean('partial_payments')->default(false)->after('payment_link_days');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_settings', fn (Blueprint $table) => $table->dropColumn(['payment_link_days', 'partial_payments']));
        Schema::dropIfExists('ecommerce_payment_links');
    }
};
