<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Regional and Localization settings (Doc 11 section 5) and the precision of
 * exchange rates (section 7), on the business itself - every page shows its
 * dates and numbers this way, and its templates start from them.
 *
 * Every business keeps writing dates and numbers as its documents did - 28 Oct
 * 2026, 1,234.50 - on A4, weeks from Monday, rates to six decimals. The fiscal
 * year start moves here from the business settings, with each business's
 * own month.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspaces', function (Blueprint $table) {
            $table->string('date_format', 10)->default('d M Y')->after('tax_rounding');
            $table->string('time_format', 3)->default('24h')->after('date_format');
            $table->string('number_format', 12)->default('comma_dot')->after('time_format');
            $table->string('week_start', 10)->default('monday')->after('number_format');
            $table->string('paper_size', 10)->default('a4')->after('week_start');
            $table->unsignedTinyInteger('fiscal_year_start_month')->default(1)->after('paper_size');
            $table->unsignedTinyInteger('rate_precision')->default(6)->after('fiscal_year_start_month');
        });

        foreach (DB::table('business_settings')->whereNotNull('workspace_id')->get(['workspace_id', 'financial_year_start_month']) as $row) {
            DB::table('workspaces')->where('id', $row->workspace_id)->update(['fiscal_year_start_month' => $row->financial_year_start_month ?: 1]);
        }

        Schema::table('business_settings', function (Blueprint $table) {
            $table->dropColumn('financial_year_start_month');
        });

        CatalogVersion::bump();
    }

    public function down(): void
    {
        Schema::table('business_settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('financial_year_start_month')->default(1)->after('tax_2_no');
        });

        foreach (DB::table('workspaces')->get(['id', 'fiscal_year_start_month']) as $row) {
            DB::table('business_settings')->where('workspace_id', $row->id)->update(['financial_year_start_month' => $row->fiscal_year_start_month]);
        }

        Schema::table('workspaces', function (Blueprint $table) {
            $table->dropColumn(['date_format', 'time_format', 'number_format', 'week_start', 'paper_size', 'fiscal_year_start_month', 'rate_precision']);
        });
    }
};
