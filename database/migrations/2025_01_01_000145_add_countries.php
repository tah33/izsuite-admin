<?php

use App\Models\Admin\Role;
use App\Support\Countries;
use App\Support\CountryDirectory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase F3a (section 15.19): the countries in a table the admin
 * edits, in place of the list in the code (App\Support\Countries), each with
 * the defaults a new business there takes - its currency, time zone and
 * regional settings, and what its addresses call their parts and how they
 * are written. A change to those is the country's next version, from a day.
 * Each starts as the list had it, as its version 1.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'product-administrator'   => ['admin.countries.index', 'admin.countries.edit', 'admin.countries.version-remove'],
        'read-only-administrator' => ['admin.countries.index'],
    ];

    public function up(): void
    {
        Schema::create('countries', function (Blueprint $table) {
            $table->id();
            $table->char('code', 2)->unique();
            $table->string('name', 100);
            $table->string('calling_code', 6)->nullable();
            $this->defaults($table);
            $table->timestamps();
        });

        Schema::create('country_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('country_id')->constrained('countries')->cascadeOnDelete();
            $table->unsignedSmallInteger('number');
            $table->date('starts_on');
            // When it started: null while it waits for its day.
            $table->timestamp('applied_at')->nullable();
            $this->defaults($table);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->unique(['country_id', 'number']);
        });

        $now   = now();
        $today = $now->toDateString();

        foreach (array_chunk(Countries::builtIn(), 50) as $chunk) {
            DB::table('countries')->insert(array_map(fn (array $row) => [...$row, 'created_at' => $now, 'updated_at' => $now], $chunk));
        }

        foreach (DB::table('countries')->get() as $country) {
            DB::table('country_versions')->insert([
                ...array_diff_key((array) $country, array_flip(['id', 'code', 'name', 'calling_code'])),
                'country_id' => $country->id,
                'number'     => 1,
                'starts_on'  => $today,
                'applied_at' => $now,
            ]);
        }

        foreach (Role::all() as $role) {
            $held        = $role->permissions ?? [];
            $permissions = array_values(array_unique([...$held, ...(self::GAINS[$role->slug] ?? [])]));

            if ($permissions !== $held) {
                $role->update(['permissions' => $permissions]);
            }
        }

        Cache::forget(CountryDirectory::CACHE_KEY);
    }

    public function down(): void
    {
        Schema::dropIfExists('country_versions');
        Schema::dropIfExists('countries');

        Cache::forget(CountryDirectory::CACHE_KEY);
    }

    /** The defaults a new business there takes (Country::DEFAULTS). */
    private function defaults(Blueprint $table): void
    {
        $table->char('currency', 3)->nullable();
        $table->string('timezone', 64)->nullable();
        $table->string('date_format', 20);
        $table->string('time_format', 5);
        $table->string('number_format', 20);
        $table->string('week_start', 10);
        $table->string('paper_size', 10);
        $table->unsignedTinyInteger('fiscal_year_start_month');
        $table->string('region_label', 40);
        $table->string('postal_label', 40);
        $table->string('address_format', 255);
    }
};
