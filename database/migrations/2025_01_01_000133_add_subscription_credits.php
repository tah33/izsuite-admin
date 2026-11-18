<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase C2b (sections 15.13 and 15.14): izSuite's credit to a
 * business, taken off its next invoices before tax - kept as a ledger, each
 * credit given, taken, given back by an invoice cancelled, or taken back -
 * and what each invoice took (`adjustment`). The starting roles gain adding
 * and removing a credit, the credits and the billing reports.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'billing-administrator'   => ['admin.subscriptions.credit', 'admin.subscriptions.credit-remove', 'admin.credits.index', 'admin.billing-reports.index'],
        'read-only-administrator' => ['admin.credits.index', 'admin.billing-reports.index'],
    ];

    public function up(): void
    {
        Schema::create('subscription_credits', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained('workspaces')->cascadeOnDelete();
            $table->char('currency', 3);
            // Signed: given (granted) and given back (returned) add; taken by an invoice (used) and taken back (removed) subtract.
            $table->decimal('amount', 12, 2);
            $table->string('kind', 20);
            $table->foreignId('subscription_invoice_id')->nullable()->constrained('subscription_invoices')->nullOnDelete();
            // The admin's reason, for one given or taken back.
            $table->text('reason')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['workspace_id', 'currency']);
            $table->index(['kind', 'created_at']);
        });

        Schema::table('subscription_invoices', function (Blueprint $table) {
            // izSuite's credit taken off it, before tax.
            $table->decimal('adjustment', 12, 2)->default(0)->after('credit');
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
            $table->dropColumn('adjustment');
        });

        Schema::dropIfExists('subscription_credits');
    }
};
