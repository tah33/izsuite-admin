<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 13 Phase A: an app's own page in the Apps Center (section 6) - a full
 * description, its main features and a support link, each edited in the
 * admin's app form. AppCatalogSeeder fills them in for the apps that are
 * built, where they are still empty.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->text('long_description')->nullable()->after('description');
            $table->json('features')->nullable()->after('long_description');
            $table->string('support_url', 255)->nullable()->after('features');
        });
    }

    public function down(): void
    {
        Schema::table('apps', function (Blueprint $table) {
            $table->dropColumn(['long_description', 'features', 'support_url']);
        });
    }
};
