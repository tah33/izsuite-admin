<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 13 Phase B1: a plan's prices, trial and grace days, and whether it
 * takes "Powered by izSuite" off customer documents (sections 2, 12 and 18),
 * all set in the admin - empty prices are not yet announced. Billing Starter
 * is free; the paid plans take the branding off, as Billing Starter alone
 * carries it.
 *
 * And each business's billing details for its izSuite subscription (section
 * 11): the name, e-mail, address, country and tax ID its invoices go to -
 * apart from the business's own profile, which its customers see.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->char('price_currency', 3)->default('USD')->after('location_limit');
            $table->decimal('price_monthly', 12, 2)->nullable()->after('price_currency');
            $table->decimal('price_yearly', 12, 2)->nullable()->after('price_monthly');
            $table->unsignedSmallInteger('trial_days')->nullable()->after('price_yearly');
            $table->unsignedSmallInteger('grace_days')->nullable()->after('trial_days');
            $table->boolean('removes_branding')->default(false)->after('grace_days');
        });

        DB::table('packages')->where('key', 'starter')->update(['price_monthly' => 0, 'price_yearly' => 0]);
        DB::table('packages')->where('key', '!=', 'starter')->update(['removes_branding' => true]);

        Schema::create('workspace_billing', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->unique()->constrained('workspaces')->cascadeOnDelete();
            $table->string('name', 150)->nullable();
            $table->string('email', 255)->nullable();
            $table->text('address')->nullable();
            $table->char('country', 2)->nullable();
            $table->string('tax_id', 50)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspace_billing');

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['price_currency', 'price_monthly', 'price_yearly', 'trial_days', 'grace_days', 'removes_branding']);
        });
    }
};
