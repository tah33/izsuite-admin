<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase D1: holding and resuming orders (section 18.22). A held
 * order is an unfinished sale put aside at its register - its cart as the
 * cashier left it, and each line's price then - numbered H-00001, kept
 * until it is resumed, cancelled or, after Settings → Point of Sale's
 * hours, expires. It is no sale: nothing of it reaches Sales, stock or the
 * payment accounts.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_held_orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number', 30);                                       // H-00001
            $table->foreignId('pos_register_id')->constrained();                // a used register is never deleted
            $table->foreignId('pos_session_id')->constrained();                 // the session it was held in
            $table->foreignId('location_id')->constrained('locations');
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();   // who held it
            $table->foreignId('contact_id')->nullable()->constrained('contacts')->nullOnDelete();
            $table->json('cart');                                               // its lines, the order's discount and note, as the cart had them
            $table->json('prices');                                             // each line's price when held
            $table->char('currency', 3);
            $table->decimal('grand_total', 15, 2)->default(0);                  // its total when held
            $table->string('status', 20)->default('held');                      // held | resumed | cancelled | expired
            $table->timestamp('expires_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();    // who resumed or cancelled it
            $table->timestamp('closed_at')->nullable();
            $table->string('cancel_reason', 500)->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['pos_register_id', 'status']);
            $table->index(['status', 'expires_at']);
        });

        Schema::table('pos_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('hold_hours')->nullable()->after('cash_rounding');   // how long a held order is kept - null for as long as it takes
        });
    }

    public function down(): void
    {
        Schema::table('pos_settings', fn (Blueprint $table) => $table->dropColumn('hold_hours'));
        Schema::dropIfExists('pos_held_orders');
    }
};
