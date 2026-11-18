<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 14 Phase D: internal notes (section 14.14). They sit beside the
 * document notes on sales documents (credit notes among them), purchases
 * and expenses. They are for the business alone and never reach a PDF,
 * print, secure link or e-mail.
 */
return new class extends Migration
{
    private const TABLES = ['sales_documents', 'purchases', 'expenses'];

    public function up(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->text('internal_notes')->nullable()->after('notes');
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $name) {
            Schema::table($name, function (Blueprint $table) {
                $table->dropColumn('internal_notes');
            });
        }
    }
};
