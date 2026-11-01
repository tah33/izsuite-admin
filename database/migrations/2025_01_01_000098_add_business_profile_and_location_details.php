<?php

use App\Services\User\Apps\CatalogVersion;
use App\Support\Countries;
use App\Support\LocationCodes;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The business profile and its locations in full (Doc 11 sections 4 and 6,
 * Doc 12 section 17).
 *
 * - business_settings gains a trading name, registration number, legal
 *   structure, industry, e-mail and two address lines. The Landmark the form
 *   used to ask for becomes the first address line. Only the name, country,
 *   language and time zone stay required, so the rest of the address may be
 *   left empty.
 * - locations gain a code - unique within the business - an address, phone,
 *   e-mail, time zone, the lines their documents add to the header, whether
 *   they are active, and which one is the business's Primary Location. Each
 *   business's oldest location becomes its Primary; codes are made from
 *   names ("Main Location" is MAIN); a time zone comes from the business's
 *   settings, else its owner's profile.
 *
 * Countries are kept as codes, as a contact's are: names saved before are
 * matched to their codes, and one that matches none is left as typed, for the
 * owner to choose again. The cached sidebars are made again, for the
 * business ID they now carry.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('trading_name', 150)->nullable()->after('business_name');
            $table->string('registration_number', 100)->nullable()->after('trading_name');
            $table->string('legal_structure', 40)->nullable()->after('registration_number');
            $table->string('industry', 100)->nullable()->after('legal_structure');
            $table->string('email')->nullable()->after('alternate_contact_number');
            $table->string('address_line_1')->nullable()->after('email');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('state', 100)->nullable()->change();
            $table->string('city', 100)->nullable()->change();
            $table->string('zip_code', 20)->nullable()->change();
        });

        DB::table('business_settings')->update(['address_line_1' => DB::raw('landmark')]);

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('landmark');
        });

        Schema::table('locations', function (Blueprint $table) {
            $table->string('code', LocationCodes::MAX)->nullable()->after('name');
            $table->boolean('is_primary')->default(false)->after('code');
            $table->boolean('is_active')->default(true)->after('is_primary');
            $table->string('phone', 30)->nullable()->after('is_active');
            $table->string('email')->nullable()->after('phone');
            $table->string('address_line_1')->nullable()->after('email');
            $table->string('address_line_2')->nullable()->after('address_line_1');
            $table->string('city', 100)->nullable()->after('address_line_2');
            $table->string('postal_code', 20)->nullable()->after('state');
            $table->string('timezone', 64)->nullable()->after('country');
            $table->text('document_header')->nullable()->after('timezone');
            $table->unique(['workspace_id', 'code']);
        });

        $this->countriesToCodes('business_settings');
        $this->countriesToCodes('locations');
        $this->fillLocations();

        CatalogVersion::bump();
    }

    public function down(): void
    {
        $this->codesToCountries('business_settings');
        $this->codesToCountries('locations');

        Schema::table('locations', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'code']);
            $table->dropColumn([
                'code', 'is_primary', 'is_active', 'phone', 'email', 'address_line_1', 'address_line_2', 'city', 'postal_code', 'timezone', 'document_header',
            ]);
        });

        Schema::table('business_settings', function (Blueprint $table) {
            $table->string('landmark')->nullable()->after('zip_code');
        });

        DB::table('business_settings')->update(['landmark' => DB::raw("COALESCE(address_line_1, '')")]);

        foreach (['state', 'city', 'zip_code'] as $column) {
            DB::table('business_settings')->whereNull($column)->update([$column => '']);
        }

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn(['trading_name', 'registration_number', 'legal_structure', 'industry', 'email', 'address_line_1', 'address_line_2']);
            $table->string('landmark')->nullable(false)->change();
            $table->string('state', 100)->nullable(false)->change();
            $table->string('city', 100)->nullable(false)->change();
            $table->string('zip_code', 20)->nullable(false)->change();
        });
    }

    /** Each business's oldest location as its Primary, and every location a code and a time zone. */
    private function fillLocations(): void
    {
        $zones = DB::table('workspaces')
            ->leftJoin('business_settings', 'business_settings.workspace_id', '=', 'workspaces.id')
            ->leftJoin('users', 'users.id', '=', 'workspaces.user_id')
            ->select(['workspaces.id as id', DB::raw('COALESCE(business_settings.timezone, users.timezone) as zone')])
            ->pluck('zone', 'id');

        foreach (DB::table('locations')->orderBy('id')->get(['id', 'workspace_id', 'name'])->groupBy('workspace_id') as $workspaceId => $locations) {
            $taken = [];

            foreach ($locations->values() as $index => $location) {
                $taken[] = $code = LocationCodes::from((string) $location->name, $taken);

                DB::table('locations')->where('id', $location->id)->update([
                    'code'       => $code,
                    'is_primary' => $index === 0,
                    'timezone'   => $zones[$workspaceId] ?? 'UTC',
                ]);
            }
        }
    }

    private function countriesToCodes(string $table): void
    {
        foreach (DB::table($table)->whereNotNull('country')->get(['id', 'country']) as $row) {
            $code = Countries::codeFor($row->country);

            if ($code && $code !== $row->country) {
                DB::table($table)->where('id', $row->id)->update(['country' => $code]);
            }
        }
    }

    private function codesToCountries(string $table): void
    {
        foreach (DB::table($table)->whereNotNull('country')->get(['id', 'country']) as $row) {
            $name = Countries::name($row->country);

            if ($name) {
                DB::table($table)->where('id', $row->id)->update(['country' => $name]);
            }
        }
    }
};
