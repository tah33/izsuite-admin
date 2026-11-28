<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase H3b - an abandoned cart's recovery (section 19.32): its
 * recovery e-mail - its link's token (only its hash), when and by whom it was
 * sent - and when the link was last opened.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_carts', function (Blueprint $table) {
            $table->char('recovery_token_hash', 64)->nullable()->unique()->after('abandoned_at');   // sha256 of its recovery link's token
            $table->timestamp('recovery_sent_at')->nullable()->after('recovery_token_hash');
            $table->foreignId('recovery_sent_by')->nullable()->after('recovery_sent_at')->constrained('users')->nullOnDelete();
            $table->timestamp('recovery_opened_at')->nullable()->after('recovery_sent_by');
        });
    }

    public function down(): void
    {
        Schema::table('ecommerce_carts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('recovery_sent_by');
            $table->dropUnique(['recovery_token_hash']);
            $table->dropColumn(['recovery_token_hash', 'recovery_sent_at', 'recovery_opened_at']);
        });
    }
};
