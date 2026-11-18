<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase C2a (section 15.14): refunds of izSuite's subscription
 * payments - a card's through its provider, a bank transfer's recorded by
 * hand - each with its reason, never deleted.
 *
 * Every payment is now one record: a bank transfer izSuite confirmed is kept
 * as a payment as a card's always was, so the ones confirmed before this get
 * theirs here. The starting roles gain the payments, the refunds and an
 * invoice's own page; any role that lists the invoices may open one.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'billing-administrator'   => ['admin.subscription-invoices.show', 'admin.payments.index', 'admin.payments.refund', 'admin.refunds.index'],
        'read-only-administrator' => ['admin.subscription-invoices.show', 'admin.payments.index', 'admin.refunds.index'],
    ];

    public function up(): void
    {
        Schema::create('subscription_refunds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->foreignId('subscription_payment_id')->constrained('subscription_payments')->cascadeOnDelete();
            $table->foreignId('subscription_invoice_id')->nullable()->constrained('subscription_invoices')->nullOnDelete();
            $table->string('provider', 20);
            // The provider's own refund (Stripe's re_...): one row each, however often it is reported.
            $table->string('reference', 100)->nullable();
            // A transfer's: the bank's reference of izSuite's transfer back, as the admin gives it.
            $table->string('transfer_reference', 100)->nullable();
            $table->char('currency', 3);
            $table->decimal('amount', 12, 2);
            $table->string('status', 20);
            $table->text('reason');
            // Why the provider did not refund it.
            $table->string('note', 255)->nullable();
            // Null for one made in the provider's own dashboard.
            $table->foreignId('refunded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('confirmed_at')->nullable();
            $table->timestamps();
            $table->unique(['provider', 'reference']);
            $table->index(['subscription_payment_id', 'status']);
            $table->index(['status', 'created_at']);
        });

        $now = now();

        // The transfers izSuite confirmed before: a paid invoice with no payment of its own, and no card's reference.
        DB::table('subscription_invoices')
            ->where('status', 'paid')
            ->whereNull('payment_reference')
            ->whereNotExists(fn ($payment) => $payment->selectRaw('1')->from('subscription_payments')
                ->whereColumn('subscription_payments.subscription_invoice_id', 'subscription_invoices.id')
                ->where('subscription_payments.status', 'succeeded'))
            ->orderBy('id')
            ->each(function ($invoice) use ($now) {
                DB::table('subscription_payments')->insert([
                    'workspace_id'            => $invoice->workspace_id,
                    'subscription_invoice_id' => $invoice->id,
                    'provider'                => 'transfer',
                    'reference'               => $invoice->number,
                    'currency'                => $invoice->currency,
                    'amount'                  => $invoice->total,
                    'status'                  => 'succeeded',
                    'attempted_on'            => $invoice->paid_at ? substr((string) $invoice->paid_at, 0, 10) : null,
                    'created_at'              => $invoice->paid_at ?? $now,
                    'updated_at'              => $now,
                ]);
            });

        foreach (Role::all() as $role) {
            $held        = $role->permissions ?? [];
            $gains       = [...(self::GAINS[$role->slug] ?? []), ...(in_array('admin.subscription-invoices.index', $held, true) ? ['admin.subscription-invoices.show'] : [])];
            $permissions = array_values(array_unique([...$held, ...$gains]));

            if ($permissions !== $held) {
                $role->update(['permissions' => $permissions]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('subscription_refunds');

        DB::table('subscription_payments')->where('provider', 'transfer')->delete();
    }
};
