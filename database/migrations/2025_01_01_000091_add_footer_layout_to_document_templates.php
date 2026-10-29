<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * How a template lays out the foot of each page (Doc 06 section 14: Footer
 * Layout): "split" - its footer text left, the page number in the middle,
 * "Powered by izSuite" right - or "centered", all three centred. Templates
 * and documents numbered before it read as split.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('document_templates', function (Blueprint $t) {
            $t->string('footer_layout', 20)->default('split')->after('footer_text');
        });
    }

    public function down(): void
    {
        Schema::table('document_templates', function (Blueprint $t) {
            $t->dropColumn('footer_layout');
        });
    }
};
