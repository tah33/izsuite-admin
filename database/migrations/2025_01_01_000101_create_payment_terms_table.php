<?php

use App\Models\User\PaymentTerm;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Payment terms (Doc 11 section 14): each business's one list of named terms
 * - "30 Days" - that its sales, purchase and expense defaults, contacts and
 * documents pick from. They keep the days, as before, so nothing already
 * saved changes. Every business starts with the suggested terms.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('payment_terms', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('name', 50);
            $table->unsignedSmallInteger('days');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
            $table->unique(['workspace_id', 'days']);
        });

        $now = now();

        foreach (DB::table('workspaces')->pluck('id') as $workspaceId) {
            DB::table('payment_terms')->insert(collect(PaymentTerm::STARTER)->map(fn (int $days, string $name) => [
                'workspace_id' => $workspaceId,
                'name'         => $name,
                'days'         => $days,
                'is_active'    => true,
                'created_at'   => $now,
                'updated_at'   => $now,
            ])->values()->all());
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_terms');
    }
};
