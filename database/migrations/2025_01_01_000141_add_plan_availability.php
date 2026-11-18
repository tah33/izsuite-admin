<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase E3a (section 15.8): a plan's reporting and support levels,
 * whether it is offered monthly and yearly, and the countries it is
 * offered in - by the business's billing country.
 */
return new class extends Migration
{
    /** key => [reporting level, support level], as the plans stood */
    private const LEVELS = [
        'starter'  => ['basic', 'email'],
        'standard' => ['standard', 'email'],
        'premium'  => ['advanced', 'priority'],
    ];

    public function up(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->string('reporting_level', 20)->nullable()->after('removes_branding');
            $table->string('support_level', 20)->nullable()->after('reporting_level');
            $table->boolean('offers_monthly')->default(true)->after('support_level');
            $table->boolean('offers_yearly')->default(true)->after('offers_monthly');
            // Empty, every country: the business's billing country.
            $table->json('countries')->nullable()->after('offers_yearly');
        });

        foreach (self::LEVELS as $key => [$reporting, $support]) {
            DB::table('packages')->where('key', $key)->update(['reporting_level' => $reporting, 'support_level' => $support]);
        }
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['reporting_level', 'support_level', 'offers_monthly', 'offers_yearly', 'countries']);
        });
    }
};
