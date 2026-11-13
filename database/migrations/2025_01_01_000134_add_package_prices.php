<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase C3a (sections 15.8 and 15.9): a plan's prices in several
 * currencies, each for a cycle from a start day - and until an end day once
 * another follows - kept as history. The plan's own currency stays its main
 * one, for a business whose currency it has no price in.
 *
 * A subscription keeps the price it pays (`price`, `currency`): a new price
 * reaches it only when izSuite applies it, from a day it is told of well
 * before (`next_price`, `next_price_from`).
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'billing-administrator' => ['admin.packages.price', 'admin.packages.price-remove', 'admin.packages.price-apply'],
    ];

    public function up(): void
    {
        Schema::create('package_prices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('package_id')->constrained('packages')->cascadeOnDelete();
            $table->char('currency', 3);
            $table->string('cycle', 10);
            $table->decimal('amount', 12, 2);
            $table->date('starts_on');
            // The day before the next price of its currency and cycle starts; open while none follows.
            $table->date('ends_on')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();
            $table->index(['package_id', 'currency', 'cycle', 'starts_on']);
        });

        Schema::table('workspace_subscriptions', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->after('cycle');
            // What its plan costs it, before credits and tax; renewals on the same plan keep it.
            $table->decimal('price', 12, 2)->nullable()->after('currency');
            $table->decimal('next_price', 12, 2)->nullable()->after('price');
            $table->date('next_price_from')->nullable()->after('next_price');
        });

        $now      = now();
        $packages = DB::table('packages')->get(['id', 'price_currency', 'price_monthly', 'price_yearly']);

        foreach ($packages as $package) {
            foreach (['monthly' => $package->price_monthly, 'yearly' => $package->price_yearly] as $cycle => $amount) {
                if ($amount === null) {
                    continue;
                }

                // In force from the start: the price every invoice so far was issued at, whatever a business's day.
                DB::table('package_prices')->insert([
                    'package_id' => $package->id, 'currency' => $package->price_currency ?: 'USD', 'cycle' => $cycle, 'amount' => $amount,
                    'starts_on'  => '2000-01-01', 'created_at' => $now, 'updated_at' => $now,
                ]);

                // Each subscription on it pays it.
                DB::table('workspace_subscriptions')->where('package_id', $package->id)->where('cycle', $cycle)
                    ->update(['currency' => $package->price_currency ?: 'USD', 'price' => $amount]);
            }
        }

        Schema::table('packages', function (Blueprint $table) {
            $table->dropColumn(['price_monthly', 'price_yearly']);
        });

        foreach (Role::all() as $role) {
            $held        = $role->permissions ?? [];
            $permissions = array_values(array_unique([...$held, ...(self::GAINS[$role->slug] ?? [])]));

            if ($permissions !== $held) {
                $role->update(['permissions' => $permissions]);
            }
        }
    }

    public function down(): void
    {
        Schema::table('packages', function (Blueprint $table) {
            $table->decimal('price_monthly', 12, 2)->nullable()->after('price_currency');
            $table->decimal('price_yearly', 12, 2)->nullable()->after('price_monthly');
        });

        // Back to one price a cycle: the plan's main currency's, open now.
        foreach (DB::table('package_prices')->whereNull('ends_on')->get() as $price) {
            DB::table('packages')->where('id', $price->package_id)->where('price_currency', $price->currency)
                ->update([$price->cycle === 'yearly' ? 'price_yearly' : 'price_monthly' => $price->amount]);
        }

        Schema::table('workspace_subscriptions', function (Blueprint $table) {
            $table->dropColumn(['currency', 'price', 'next_price', 'next_price_from']);
        });

        Schema::dropIfExists('package_prices');
    }
};
