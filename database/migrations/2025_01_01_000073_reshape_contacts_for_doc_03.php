<?php

use App\Support\Countries;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Contacts as Doc 03 describes them:
 *
 * - contact_number: C-00001, counted per business (the "contact" counter of
 *   workspace_sequences). Existing contacts are numbered in the order they
 *   were made.
 * - contact_type: business or individual. Existing contacts become business
 *   contacts.
 * - role: customer, supplier or both - one value instead of the is_customer /
 *   is_vendor pair.
 * - company_name becomes trading_name; nobody's typed name moves.
 * - A billing and a shipping address. The old address becomes the billing
 *   one, and its free-text country a country code; a country the list does
 *   not know is kept as the address's second line, so nothing is lost.
 * - Contact person, job title, a second phone, website, preferred language,
 *   tags, the sales and purchase defaults, and the tax country and exemption
 *   number.
 */
return new class extends Migration
{
    private const NEW_COLUMNS = [
        'contact_number', 'contact_type', 'role', 'contact_person', 'job_title', 'alt_phone', 'website',
        'preferred_language', 'tags', 'billing_line2', 'billing_country', 'shipping_same_as_billing',
        'shipping_line1', 'shipping_line2', 'shipping_city', 'shipping_state', 'shipping_postal_code',
        'shipping_country', 'sales_currency', 'customer_payment_terms', 'purchase_currency',
        'supplier_payment_terms', 'tax_country', 'tax_exemption_number',
    ];

    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            $t->string('contact_number', 20)->nullable()->after('workspace_id');
            $t->string('contact_type', 12)->default('business')->after('contact_number');
            $t->string('role', 10)->default('customer')->after('contact_type');
            $t->string('contact_person')->nullable()->after('company_name');
            $t->string('job_title', 100)->nullable()->after('contact_person');
            $t->string('alt_phone', 30)->nullable()->after('phone');
            $t->string('website')->nullable()->after('alt_phone');
            $t->string('preferred_language', 10)->nullable()->after('website');
            $t->json('tags')->nullable()->after('preferred_language');
            $t->string('billing_line2')->nullable()->after('address');
            $t->char('billing_country', 2)->nullable()->after('postal_code');
            $t->boolean('shipping_same_as_billing')->default(true)->after('billing_country');
            $t->string('shipping_line1')->nullable()->after('shipping_same_as_billing');
            $t->string('shipping_line2')->nullable()->after('shipping_line1');
            $t->string('shipping_city', 100)->nullable()->after('shipping_line2');
            $t->string('shipping_state', 100)->nullable()->after('shipping_city');
            $t->string('shipping_postal_code', 20)->nullable()->after('shipping_state');
            $t->char('shipping_country', 2)->nullable()->after('shipping_postal_code');
            $t->char('sales_currency', 3)->nullable()->after('shipping_country');
            $t->unsignedSmallInteger('customer_payment_terms')->nullable()->after('sales_currency');
            $t->char('purchase_currency', 3)->nullable()->after('customer_payment_terms');
            $t->unsignedSmallInteger('supplier_payment_terms')->nullable()->after('purchase_currency');
            $t->char('tax_country', 2)->nullable()->after('tax_number');
            $t->string('tax_exemption_number', 50)->nullable()->after('tax_country');
        });

        Schema::table('contacts', function (Blueprint $t) {
            $t->renameColumn('company_name', 'trading_name');
            $t->renameColumn('address', 'billing_line1');
            $t->renameColumn('city', 'billing_city');
            $t->renameColumn('state', 'billing_state');
            $t->renameColumn('postal_code', 'billing_postal_code');
        });

        DB::table('contacts')->where('is_vendor', true)->where('is_customer', true)->update(['role' => 'both']);
        DB::table('contacts')->where('is_vendor', true)->where('is_customer', false)->update(['role' => 'supplier']);

        $this->turnCountriesIntoCodes();
        $this->numberContacts();

        Schema::table('contacts', function (Blueprint $t) {
            $t->string('contact_number', 20)->nullable(false)->change();
            $t->unique(['workspace_id', 'contact_number']);
        });

        Schema::table('contacts', function (Blueprint $t) {
            $t->dropColumn(['is_customer', 'is_vendor', 'country']);
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $t) {
            $t->boolean('is_customer')->default(true)->after('workspace_id');
            $t->boolean('is_vendor')->default(false)->after('is_customer');
            $t->string('country', 100)->nullable()->after('billing_state');
        });

        DB::table('contacts')->where('role', 'supplier')->update(['is_customer' => false, 'is_vendor' => true]);
        DB::table('contacts')->where('role', 'both')->update(['is_vendor' => true]);

        foreach (DB::table('contacts')->whereNotNull('billing_country')->get(['id', 'billing_country']) as $row) {
            DB::table('contacts')->where('id', $row->id)->update(['country' => Countries::name($row->billing_country)]);
        }

        Schema::table('contacts', function (Blueprint $t) {
            $t->dropUnique(['workspace_id', 'contact_number']);
        });

        Schema::table('contacts', function (Blueprint $t) {
            $t->renameColumn('trading_name', 'company_name');
            $t->renameColumn('billing_line1', 'address');
            $t->renameColumn('billing_city', 'city');
            $t->renameColumn('billing_state', 'state');
            $t->renameColumn('billing_postal_code', 'postal_code');
        });

        Schema::table('contacts', function (Blueprint $t) {
            $t->dropColumn(self::NEW_COLUMNS);
        });

        if (Schema::hasTable('workspace_sequences')) {
            DB::table('workspace_sequences')->where('name', 'contact')->delete();
        }
    }

    private function turnCountriesIntoCodes(): void
    {
        $rows = DB::table('contacts')->whereNotNull('country')->where('country', '!=', '')->get(['id', 'country']);

        foreach ($rows as $row) {
            $code = Countries::codeFor($row->country);

            DB::table('contacts')->where('id', $row->id)->update(
                $code ? ['billing_country' => $code] : ['billing_line2' => mb_substr($row->country, 0, 255)],
            );
        }
    }

    /** C-00001 onwards within each business, oldest first, and each business's counter left at its last. */
    private function numberContacts(): void
    {
        $last = [];

        foreach (DB::table('contacts')->orderBy('workspace_id')->orderBy('id')->get(['id', 'workspace_id']) as $row) {
            $next = $last[$row->workspace_id] = ($last[$row->workspace_id] ?? 0) + 1;

            DB::table('contacts')->where('id', $row->id)->update(['contact_number' => sprintf('C-%05d', $next)]);
        }

        $now = now();

        foreach ($last as $workspaceId => $value) {
            DB::table('workspace_sequences')->updateOrInsert(
                ['workspace_id' => $workspaceId, 'name' => 'contact'],
                ['last_value' => $value, 'created_at' => $now, 'updated_at' => $now],
            );
        }
    }
};
