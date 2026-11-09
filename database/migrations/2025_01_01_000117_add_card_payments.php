<?php

use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 13 Phase D2: paying izSuite's invoices by card, through Stripe.
 *
 * - An online gateway's keys are kept encrypted (Doc 15 section 15), so the
 *   column holds text, not JSON; any kept until now is encrypted.
 * - A business's card for its subscription: the provider's customer and
 *   card, and what the Subscription page shows of it. One per business.
 * - Each card payment, once however often it is reported - and on the
 *   invoice it paid, what paid it: "Visa ending 4242" and its reference.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('payment_methods', function (Blueprint $table) {
            $table->text('credentials')->nullable()->change();
        });

        foreach (DB::table('payment_methods')->whereNotNull('credentials')->get(['id', 'credentials']) as $row) {
            try {
                Crypt::decryptString($row->credentials);
            } catch (DecryptException) {
                DB::table('payment_methods')->where('id', $row->id)->update(['credentials' => Crypt::encryptString($row->credentials)]);
            }
        }

        Schema::create('workspace_cards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
            $table->string('provider', 20);
            // Customers and cards of a provider's test mode are not its live mode's.
            $table->boolean('livemode');
            $table->string('customer_id', 100);
            $table->string('card_id', 100)->nullable();
            $table->string('brand', 30)->nullable();
            $table->string('last4', 4)->nullable();
            $table->unsignedTinyInteger('exp_month')->nullable();
            $table->unsignedSmallInteger('exp_year')->nullable();
            $table->timestamps();
        });

        Schema::create('subscription_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('subscription_invoice_id')->nullable()->constrained('subscription_invoices')->nullOnDelete();
            $table->string('provider', 20);
            $table->string('reference', 100);
            $table->char('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20);
            $table->string('card', 60)->nullable();
            // Why a payment did not pay its invoice: cancelled before it arrived, or not its total.
            $table->string('note', 255)->nullable();
            $table->timestamps();

            $table->unique(['provider', 'reference']);
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->string('paid_with', 60)->nullable()->after('transfer_reference');
            $table->string('payment_reference', 100)->nullable()->after('paid_with');
        });
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropColumn(['paid_with', 'payment_reference']);
        });

        Schema::dropIfExists('subscription_payments');
        Schema::dropIfExists('workspace_cards');

        foreach (DB::table('payment_methods')->whereNotNull('credentials')->get(['id', 'credentials']) as $row) {
            try {
                DB::table('payment_methods')->where('id', $row->id)->update(['credentials' => Crypt::decryptString($row->credentials)]);
            } catch (DecryptException) {
            }
        }

        Schema::table('payment_methods', function (Blueprint $table) {
            $table->json('credentials')->nullable()->change();
        });
    }
};
