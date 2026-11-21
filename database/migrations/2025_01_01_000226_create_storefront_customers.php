<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase E1 - storefront customer accounts (section 19.16), apart
 * from the business's users:
 *
 * - storefront_customers: an account of the business - one per e-mail, kept
 *   in small letters - signing its customer in on each of its storefronts:
 *   name, phone, password, when its e-mail was verified, active or closed,
 *   the storefront it signed up on, its contact (Phase E2);
 * - storefront_customer_tokens: its sign-ins, each a token's hash, used and
 *   expiring;
 * - storefront_customer_codes: the codes e-mailed to it - to verify its
 *   e-mail, or to reset its password - each a hash, with its tries.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('storefront_customers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('contact_id')->nullable()->constrained()->nullOnDelete();     // Phase E2
            $table->foreignId('storefront_id')->nullable()->constrained()->nullOnDelete();  // where it signed up
            $table->string('name', 150);
            $table->string('email', 255);                                                   // in small letters
            $table->string('phone', 40)->nullable();
            $table->string('password');
            $table->timestamp('email_verified_at')->nullable();
            $table->string('status', 10)->default('active');                                // active | closed
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('last_signed_in_at')->nullable();
            $table->timestamps();

            $table->unique(['workspace_id', 'email']);
        });

        Schema::create('storefront_customer_tokens', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_customer_id')->constrained()->cascadeOnDelete();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('expires_at')->index();
            $table->timestamps();
        });

        Schema::create('storefront_customer_codes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('storefront_customer_id')->constrained()->cascadeOnDelete();
            $table->string('purpose', 10);                                                  // verify | reset
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->timestamps();

            $table->index(['storefront_customer_id', 'purpose']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('storefront_customer_codes');
        Schema::dropIfExists('storefront_customer_tokens');
        Schema::dropIfExists('storefront_customers');
    }
};
