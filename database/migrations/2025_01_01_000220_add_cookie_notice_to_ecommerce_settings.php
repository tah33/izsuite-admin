<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase C6b: Settings → eCommerce's cookie and consent settings
 * (section 19.37) - whether the storefronts show their visitors a cookie
 * notice (on), and its words: empty for izSuite's own.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->boolean('cookie_notice')->default(true)->after('order_prefix');
            $table->string('cookie_notice_text', 300)->nullable()->after('cookie_notice');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->dropColumn(['cookie_notice', 'cookie_notice_text']);
        });
    }
};
