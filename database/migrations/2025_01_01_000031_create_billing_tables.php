<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('categories')) {
            Schema::create('categories', function (Blueprint $table) {
                $table->id();
                $table->string('name');
                $table->string('slug')->unique();
                $table->string('icon')->nullable();
                $table->string('color')->nullable();
                $table->boolean('is_system')->default(false);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('subscriptions')) {
            Schema::create('subscriptions', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->unsignedBigInteger('plan_id')->nullable();
                $table->string('plan_slug')->nullable();
                $table->foreignId('category_id')->nullable()->constrained()->nullOnDelete();
                $table->string('name');
                $table->text('description')->nullable();
                $table->string('logo_url')->nullable();
                $table->string('website_url')->nullable();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('payment_id')->nullable();
                $table->string('payment_method_slug')->nullable();
                $table->unsignedInteger('job_postings_limit')->nullable();
                $table->unsignedInteger('job_postings_used')->default(0);
                $table->unsignedInteger('ai_screenings_limit')->nullable();
                $table->unsignedInteger('ai_screenings_used')->default(0);
                $table->unsignedInteger('team_members_limit')->nullable();
                $table->unsignedInteger('team_members_used')->default(0);
                $table->string('billing_cycle')->default('monthly');
                $table->unsignedTinyInteger('billing_day')->nullable();
                $table->date('start_date')->nullable();
                $table->date('next_renewal_date')->nullable();
                $table->date('last_charged_date')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->string('status')->default('active');
                $table->string('usage_status')->default('medium');
                $table->unsignedTinyInteger('confidence_score')->nullable();
                $table->boolean('is_manual')->default(true);
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index('next_renewal_date');
            });
        }

        if (! Schema::hasTable('invoices')) {
            Schema::create('invoices', function (Blueprint $table) {
                $table->id();
                $table->foreignId('user_id')->constrained()->cascadeOnDelete();
                $table->foreignId('subscription_id')->nullable()->constrained()->nullOnDelete();
                $table->string('invoice_number')->unique();
                $table->decimal('amount', 10, 2)->default(0);
                $table->string('currency', 3)->default('USD');
                $table->string('status')->default('pending');
                $table->string('payment_method')->nullable();
                $table->string('payment_id')->nullable();
                $table->string('transaction_id')->nullable();
                $table->string('proof_image')->nullable();
                $table->timestamp('paid_at')->nullable();
                $table->text('description')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index('payment_id');
            });
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('invoices');
        Schema::dropIfExists('subscriptions');
        Schema::dropIfExists('categories');
    }
};
