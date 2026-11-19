<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase B1: POS sessions (sections 18.6, 18.27 and 18.28). A cashier
 * opens one on a register, with the opening cash float, and closes it with
 * the cash counted: each closing is kept as its Z report - what the session
 * took, the cash expected and counted, and the variance with its reason -
 * and never changed. Point of Sale's menu gains Sessions.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_sessions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('number', 20);                                  // S-00001, the business's own
            $table->foreignId('pos_register_id')->constrained();           // a register with sessions is never deleted
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->char('currency', 3);                                   // the register's, when it opened
            $table->string('status', 20)->default('open');                 // open, closing, closed, suspended
            $table->timestamp('opened_at');
            $table->decimal('opening_float', 15, 2)->default(0);
            $table->text('opening_notes')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['workspace_id', 'number']);
            $table->index(['pos_register_id', 'status']);
            $table->index(['workspace_id', 'status', 'opened_at']);
        });

        Schema::create('pos_session_closings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('pos_session_id')->constrained()->cascadeOnDelete();
            $table->decimal('opening_float', 15, 2);
            $table->decimal('expected_cash', 15, 2);
            $table->decimal('counted_cash', 15, 2);
            $table->decimal('variance', 15, 2);                            // counted less expected
            $table->string('variance_reason', 500)->nullable();
            $table->text('notes')->nullable();
            $table->json('totals');                                        // what the session took, as PosSessionTotals gives it
            $table->foreignId('closed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('closed_at');
            $table->timestamps();
        });

        // Into a menu that exists, after its last item: a fresh install gets it from AppCatalogSeeder.
        $now  = now();
        $last = DB::table('menu_items')->max('sort_order');

        if ($last !== null && ! DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/sessions')->exists()) {
            DB::table('menu_items')->insert([
                'section' => 'apps', 'family_key' => 'pos', 'label' => 'Sessions', 'icon' => 'clock', 'route' => '/pos/sessions', 'min_edition' => 'lite',
                'permission_key' => 'pos.sessions.view', 'sort_order' => $last + 10, 'is_active' => true, 'created_at' => $now, 'updated_at' => $now,
            ]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('family_key', 'pos')->where('route', '/pos/sessions')->delete();
        CatalogVersion::bump();

        Schema::dropIfExists('pos_session_closings');
        Schema::dropIfExists('pos_sessions');
    }
};
