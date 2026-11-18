<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase C3: merging contacts (section 17.21). A contact merged into
 * another is kept, archived, with its financial records, and says which one
 * it was merged into, when and by whom.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->foreignId('merged_into_id')->nullable()->after('is_active')->constrained('contacts')->nullOnDelete();
            $table->timestamp('merged_at')->nullable()->after('merged_into_id');
            $table->foreignId('merged_by')->nullable()->after('merged_at')->constrained('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('contacts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('merged_by');
            $table->dropColumn('merged_at');
            $table->dropConstrainedForeignId('merged_into_id');
        });
    }
};
