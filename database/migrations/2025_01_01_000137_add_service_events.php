<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase D3a (section 15.23): what outside services did - a card
 * provider's webhook taken or refused, a call to it that failed - for Admin
 * > Service Health, kept 30 days.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'compliance-and-security-administrator' => ['admin.service-health.index', 'admin.service-health.retry', 'admin.service-health.forget'],
        'billing-administrator'                 => ['admin.service-health.index'],
        'support-administrator'                 => ['admin.service-health.index'],
        'read-only-administrator'               => ['admin.service-health.index'],
    ];

    public function up(): void
    {
        Schema::create('service_events', function (Blueprint $table) {
            $table->id();
            // The service - stripe - and what happened: a webhook, or a call to it.
            $table->string('service', 30);
            $table->string('kind', 30);
            $table->boolean('ok');
            $table->string('message', 500)->nullable();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['service', 'kind', 'created_at']);
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
        Schema::dropIfExists('service_events');
    }
};
