<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 18 Phase A1: Point of Sale comes on. The Apps Center offers it - it was
 * coming soon - where an admin has not changed it since. Settings → Point of
 * Sale keeps the business's walk-in customer and its receipts' return policy
 * and footer (sections 18.4, 18.11 and 18.32), and its payment methods
 * (section 18.15): each a name, a type, the payment account it takes money
 * into, whether it needs a reference and may give change, and whether it is
 * on, in their order. One whose account goes keeps none until given another.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('pos_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained()->cascadeOnDelete();
            $table->foreignId('walk_in_contact_id')->nullable()->constrained('contacts')->nullOnDelete();   // none: no customer named
            $table->text('return_policy')->nullable();
            $table->string('receipt_footer')->nullable();
            $table->timestamps();
        });

        Schema::create('pos_payment_methods', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('type', 20);                                                     // cash | card | mobile_money | bank_transfer | other
            $table->foreignId('payment_account_id')->nullable()->constrained('payment_accounts')->nullOnDelete();
            $table->boolean('needs_reference')->default(false);
            $table->boolean('gives_change')->default(false);                                // cash only
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        DB::table('apps')->where('app_key', 'pos')->where('status', 'coming_soon')->update(['status' => 'available', 'updated_at' => now()]);
        CatalogVersion::bump();
    }

    public function down(): void
    {
        Schema::dropIfExists('pos_payment_methods');
        Schema::dropIfExists('pos_settings');

        DB::table('apps')->where('app_key', 'pos')->where('status', 'available')->update(['status' => 'coming_soon', 'updated_at' => now()]);
        CatalogVersion::bump();
    }
};
