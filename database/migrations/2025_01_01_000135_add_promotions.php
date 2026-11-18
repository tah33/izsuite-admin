<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase C3b (section 15.17): promotion codes - a percent or a fixed
 * amount off a new subscription's first invoice, for the plans, cycles and
 * countries chosen, between two days, up to a total and per business, new
 * customers only if wished. The invoice keeps its discount and the code.
 */
return new class extends Migration
{
    /** slug => the routes it gains (a create or edit route lets its form save too) */
    private const GAINS = [
        'billing-administrator'   => ['admin.promotions.index', 'admin.promotions.create', 'admin.promotions.edit', 'admin.promotions.destroy'],
        'read-only-administrator' => ['admin.promotions.index'],
    ];

    public function up(): void
    {
        Schema::create('promotions', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100);
            // Kept in capitals; a business types it in any case.
            $table->string('code', 40)->unique();
            $table->string('discount_type', 10);
            $table->decimal('discount_value', 12, 2);
            // A fixed amount's currency: it takes off only invoices in it.
            $table->char('currency', 3)->nullable();
            // Empty, all of them: the plans by key, the cycles, the countries of the billing details.
            $table->json('packages')->nullable();
            $table->json('cycles')->nullable();
            $table->json('countries')->nullable();
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->unsignedInteger('max_redemptions')->nullable();
            $table->unsignedSmallInteger('per_business')->default(1);
            $table->boolean('new_customers_only')->default(false);
            $table->string('status', 10)->default('active');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            // A promotion's discount, off the price before credits and tax, and its code as it was.
            $table->decimal('discount', 12, 2)->default(0)->after('amount');
            $table->foreignId('promotion_id')->nullable()->after('discount')->constrained('promotions')->nullOnDelete();
            $table->string('promotion_code', 40)->nullable()->after('promotion_id');
        });

        foreach (Role::all() as $role) {
            $held        = $role->permissions ?? [];
            $permissions = array_values(array_unique([...$held, ...(self::GAINS[$role->slug] ?? [])]));

            if ($permissions !== $held) {
                $role->update(['permissions' => $permissions]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('subscription_invoices', function (Blueprint $table) {
            $table->dropConstrainedForeignId('promotion_id');
            $table->dropColumn(['discount', 'promotion_code']);
        });

        Schema::dropIfExists('promotions');
    }
};
