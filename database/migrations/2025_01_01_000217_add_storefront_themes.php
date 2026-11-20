<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase C4a: a storefront's theme and branding (section 19.7) - its
 * logo and favicon (pictures of its own), colours, font, header, product
 * cards, footer, contact details and social links. The one its customers see
 * is kept apart from the draft its team changes and previews; publishing puts
 * the draft in its place. None yet: the defaults.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('storefronts', function (Blueprint $table) {
            $table->json('theme')->nullable()->after('support_phone');              // what its customers see
            $table->json('theme_draft')->nullable()->after('theme');                // what its team is changing
            $table->timestamp('theme_published_at')->nullable()->after('theme_draft');
        });
    }

    public function down(): void
    {
        Schema::table('storefronts', function (Blueprint $table) {
            $table->dropColumn(['theme', 'theme_draft', 'theme_published_at']);
        });
    }
};
