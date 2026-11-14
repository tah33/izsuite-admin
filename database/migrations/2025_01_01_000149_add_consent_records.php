<?php

use App\Models\Admin\Role;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 15 Phase G3 (section 15.24): consent records - each time an account
 * accepted the terms, or gave or withdrew its consent to izSuite's product
 * news by e-mail, where from, never changed - and the account's marketing
 * choice as it stands. Each account that accepted the terms at sign-up has
 * that recorded, on its day. Data retention is shown in the admin.
 */
return new class extends Migration
{
    /** slug => the routes it gains */
    private const GAINS = [
        'compliance-and-security-administrator' => ['admin.retention.index', 'admin.retention.update'],
        'read-only-administrator'               => ['admin.retention.index'],
    ];

    public function up(): void
    {
        Schema::create('consent_records', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            // The account's e-mail when it was given: kept with it.
            $table->string('email', 190);
            $table->string('kind', 20);
            $table->boolean('given');
            $table->string('source', 20);
            $table->string('ip_address', 45)->nullable();
            $table->string('user_agent', 500)->nullable();
            $table->timestamp('created_at');
            $table->index(['user_id', 'kind', 'id']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->boolean('marketing_consent')->default(false)->after('terms_accepted_at');
        });

        foreach (DB::table('users')->whereNotNull('terms_accepted_at')->orderBy('id')->get(['id', 'email', 'terms_accepted_at']) as $user) {
            DB::table('consent_records')->insert([
                'user_id' => $user->id, 'email' => $user->email, 'kind' => 'terms', 'given' => true, 'source' => 'registration', 'created_at' => $user->terms_accepted_at,
            ]);
        }

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
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('marketing_consent');
        });

        Schema::dropIfExists('consent_records');
    }
};
