<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase A3: a storefront's own domains (section 19.5). Each is a name
 * no other storefront of izSuite has, with the token its TXT record must
 * show; verified once it does, and looked up when asked. One verified domain
 * is the storefront's primary one - with none, its izSuite address is.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_domains', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('storefront_id')->constrained()->cascadeOnDelete();
            $table->string('host', 253)->unique();                                          // in small letters, unique across izSuite
            $table->string('token', 64);                                                    // what its TXT record shows
            $table->timestamp('verified_at')->nullable();
            $table->timestamp('checked_at')->nullable();                                    // its DNS last looked up
            $table->boolean('is_primary')->default(false);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['storefront_id', 'is_primary']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_domains');
    }
};
