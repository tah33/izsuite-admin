<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 19 Phase G3b - secure downloads of an online order's digital products
 * (section 19.28):
 *
 * - ecommerce_settings: for how many days a delivered download works (30),
 *   how many times each of its files may be downloaded (5) - each null for no
 *   limit - and whether the customer must be signed in to their account (no);
 * - ecommerce_order_deliveries: a download's terms as it was delivered, or
 *   given more - until when it works, how many times each file, and the
 *   moment its count starts from;
 * - ecommerce_order_downloads: each download - which file, by whom when
 *   signed in, from where, and when.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ecommerce_settings', function (Blueprint $table) {
            $table->unsignedSmallInteger('download_days')->nullable()->default(30)->after('partial_payments');
            $table->unsignedSmallInteger('download_limit')->nullable()->default(5)->after('download_days');
            $table->boolean('downloads_need_account')->default(false)->after('download_limit');
        });

        Schema::table('ecommerce_order_deliveries', function (Blueprint $table) {
            $table->timestamp('expires_at')->nullable()->after('delivered_by');
            $table->unsignedSmallInteger('download_limit')->nullable()->after('expires_at');
            $table->timestamp('counted_from')->nullable()->after('download_limit');
        });

        Schema::create('ecommerce_order_downloads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('ecommerce_order_delivery_id')->constrained()->cascadeOnDelete();
            $table->foreignId('workspace_file_id')->nullable()->constrained()->nullOnDelete();
            $table->string('file_name');
            $table->foreignId('storefront_customer_id')->nullable()->constrained()->nullOnDelete();
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('downloaded_at');

            // Named here: Laravel's own name for it runs past MySQL's 64 characters.
            $table->index(['ecommerce_order_delivery_id', 'workspace_file_id'], 'ecommerce_downloads_delivery_file_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ecommerce_order_downloads');
        Schema::table('ecommerce_order_deliveries', fn (Blueprint $table) => $table->dropColumn(['expires_at', 'download_limit', 'counted_from']));
        Schema::table('ecommerce_settings', fn (Blueprint $table) => $table->dropColumn(['download_days', 'download_limit', 'downloads_need_account']));
    }
};
