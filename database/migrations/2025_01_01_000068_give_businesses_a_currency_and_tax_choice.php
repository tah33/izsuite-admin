<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * A business's base currency and whether it uses taxes (Doc 01 sections 11 and
 * 12) belong to the business itself, so they live on workspaces - there from
 * the moment a business is made, whatever else it has saved:
 *
 * - base_currency: the one currency the Dashboard and reports add up in. A new
 *   business starts on the platform's default currency.
 * - additional_currencies: the other currencies it takes or pays in, switched
 *   on in Settings. Each payment account uses the base currency or one of these.
 * - tax_mode: enabled, disabled, or later (not decided yet) - the three answers
 *   Doc 01 gives business setup. Tax fields show only when it is enabled.
 *
 * business_settings.currency was the business's currency in all but name: its
 * value becomes base_currency and the column goes, so there is one source. A
 * business that has recorded tax registration details starts with taxes
 * enabled; every other one starts on "later".
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $t) {
            $t->char('base_currency', 3)->nullable()->after('package_id');
            $t->json('additional_currencies')->nullable()->after('base_currency');
            $t->string('tax_mode', 10)->default('later')->after('additional_currencies');
        });

        DB::table('workspaces')->update(['base_currency' => $this->platformCurrency()]);

        DB::table('workspaces')
            ->whereExists($this->settingsOfTheWorkspace())
            ->update(['base_currency' => DB::raw('(select bs.currency from business_settings bs where bs.workspace_id = workspaces.id)')]);

        DB::table('workspaces')
            ->whereExists(fn (Builder $q) => $this->settingsOfTheWorkspace()($q)->where(fn (Builder $q) => $q
                ->whereNotNull('tax_1_name')->where('tax_1_name', '!=', '')
                ->orWhere(fn (Builder $q) => $q->whereNotNull('tax_1_no')->where('tax_1_no', '!=', ''))
                ->orWhere(fn (Builder $q) => $q->whereNotNull('tax_2_name')->where('tax_2_name', '!=', ''))
                ->orWhere(fn (Builder $q) => $q->whereNotNull('tax_2_no')->where('tax_2_no', '!=', ''))))
            ->update(['tax_mode' => 'enabled']);

        Schema::table('workspaces', function (Blueprint $t) {
            $t->char('base_currency', 3)->nullable(false)->change();
        });

        Schema::table('business_settings', function (Blueprint $t) {
            $t->dropColumn('currency');
        });
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $t) {
            $t->string('currency', 3)->nullable()->after('start_date');
        });

        DB::table('business_settings')->update([
            'currency' => DB::raw('(select w.base_currency from workspaces w where w.id = business_settings.workspace_id)'),
        ]);

        Schema::table('business_settings', function (Blueprint $t) {
            $t->string('currency', 3)->nullable(false)->change();
        });

        Schema::table('workspaces', function (Blueprint $t) {
            $t->dropColumn(['base_currency', 'additional_currencies', 'tax_mode']);
        });
    }

    /** The currency an admin has marked default, or the first active one. */
    private function platformCurrency(): string
    {
        return DB::table('currencies')->where('is_active', true)->where('is_default', true)->value('code')
            ?? DB::table('currencies')->where('is_active', true)->orderBy('id')->value('code')
            ?? 'USD';
    }

    /** "This workspace's business settings row", for whereExists. */
    private function settingsOfTheWorkspace(): Closure
    {
        return fn (Builder $q) => $q->select(DB::raw(1))
            ->from('business_settings')
            ->whereColumn('business_settings.workspace_id', 'workspaces.id');
    }
};
