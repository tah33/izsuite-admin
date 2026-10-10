<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->enum('plan_for', ['recruiter', 'candidate'])->default('recruiter');
            $table->enum('billing_type', ['monthly', 'yearly'])->default('monthly');
            $table->text('description')->nullable();
            $table->decimal('monthly_price', 10, 2)->default(0);
            $table->decimal('yearly_price', 10, 2)->default(0);
            $table->unsignedSmallInteger('trial_days')->default(0);
            $table->json('features')->nullable();
            $table->unsignedInteger('job_postings_limit')->nullable();
            $table->unsignedInteger('ai_screenings_limit')->nullable();
            $table->unsignedInteger('team_members_limit')->nullable();
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('plan_payment_providers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->onDelete('cascade');
            $table->string('provider');            // stripe, paypal, razorpay, etc.
            $table->enum('interval', ['monthly', 'yearly']);
            $table->string('provider_price_id');   // e.g. Stripe price_xxx
            $table->timestamps();

            $table->unique(['plan_id', 'provider', 'interval']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('plan_payment_providers');
        Schema::dropIfExists('plans');
    }
};
