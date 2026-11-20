<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase F1: cash movements (section 18.26) - cash put into or taken out
 * of a session's drawer, each with why: cash in, cash out, a safe drop, a
 * bank deposit or a float adjustment. Each moves money between the drawer's
 * cash account and another of the business's accounts, by a transfer. Above
 * Settings → Point of Sale's limit, one waits for a manager's approval:
 * nothing moves until it is approved, nor if it is declined.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_cash_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);                                          // CM-00001
            $table->foreignId('pos_session_id')->constrained();                    // the session whose drawer it moves
            $table->foreignId('pos_register_id')->constrained();
            $table->foreignId('location_id')->constrained('locations');
            $table->string('type', 20);                                            // cash_in | cash_out | safe_drop | bank_deposit | float_adjustment
            $table->string('direction', 3);                                        // in | out of the drawer
            $table->decimal('amount', 15, 2);
            $table->char('currency', 3);
            $table->foreignId('drawer_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();   // the drawer's cash account
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();  // the account it moves to or from
            $table->foreignId('account_transfer_id')->nullable()->constrained('account_transfers')->nullOnDelete(); // the transfer, once it moved
            $table->string('reason', 500);
            $table->string('status', 20)->default('completed');                   // completed | pending | declined
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('decided_by')->nullable()->constrained('users')->nullOnDelete();                   // who approved or declined it
            $table->timestamp('decided_at')->nullable();
            $table->string('decline_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['pos_session_id', 'status']);
        });

        Schema::table('pos_settings', function (Blueprint $table) {
            // Above it, a cash movement waits for a manager's approval; none, none waits.
            $table->decimal('cash_movement_limit', 15, 2)->nullable()->after('variance_limit');
        });
    }

    public function down(): void
    {
        Schema::table('pos_settings', fn (Blueprint $table) => $table->dropColumn('cash_movement_limit'));
        Schema::dropIfExists('pos_cash_movements');
    }
};
