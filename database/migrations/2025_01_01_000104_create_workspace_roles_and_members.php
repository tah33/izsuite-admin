<?php

use App\Services\User\Apps\CatalogVersion;
use App\Support\Permissions;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Roles and the people who hold them in a business (Doc 12 sections 4, 10
 * and 11).
 *
 * workspace_roles: each business's own roles - the default templates
 * (`template` names which; Business Owner is the protected one) and those it
 * adds. A role holds permissions (Permissions), or every one there is with
 * full_access. Names are unique within the business.
 *
 * workspace_members: who belongs to a business, with which role. Its owner is
 * one, under Business Owner; others join by invitation, which brings the rest
 * of a member's details. A role someone holds cannot be deleted.
 *
 * Every business gets the templates - their permissions among the apps it has
 * on today - and its owner as a member. The sidebar's items get the
 * permission each needs, where an admin has not set one.
 */
return new class extends Migration
{
    private const MENU_PERMISSIONS = [
        '/dashboard'          => 'dashboard.view',
        '/contacts'           => 'contacts.view',
        '/items'              => 'items.view',
        '/payment-accounts'   => 'payment_accounts.view',
        '/documents'          => 'documents.view',
        '/reports'            => 'reports.view',
        '/sales/invoices'     => 'sales.invoices.view',
        '/sales/quotations'   => 'sales.quotations.view',
        '/sales/proformas'    => 'sales.proformas.view',
        '/sales/credit-notes' => 'sales.credit_notes.view',
        '/sales/payments'     => 'sales.payments.view',
        '/purchases'          => 'purchases.purchases.view',
        '/purchases/payments' => 'purchases.payments.view',
        '/expenses'           => 'expenses.expenses.view',
        '/expenses/payments'  => 'expenses.payments.view',
        '/staff'              => 'settings.users',
        '/apps-center'        => 'settings.apps',
        '/settings'           => 'settings.view',
    ];

    public function up(): void
    {
        Schema::create('workspace_roles', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->string('template', 30)->nullable();
            $table->string('name', 100);
            $table->string('description')->nullable();
            $table->boolean('full_access')->default(false);
            $table->json('permissions')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['workspace_id', 'name']);
        });

        Schema::create('workspace_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workspace_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained('workspace_roles')->restrictOnDelete();
            $table->string('status', 20)->default('active');
            $table->timestamps();

            $table->unique(['workspace_id', 'user_id']);
        });

        $now = now();

        foreach (DB::table('workspaces')->get(['id', 'user_id']) as $workspace) {
            $families = DB::table('workspace_apps')->where('workspace_id', $workspace->id)->where('status', 'active')->pluck('family_key')->all();

            foreach (Permissions::TEMPLATES as $template => [$name, $description]) {
                $roleId = DB::table('workspace_roles')->insertGetId([
                    'workspace_id' => $workspace->id,
                    'template'     => $template,
                    'name'         => $name,
                    'description'  => $description,
                    'full_access'  => in_array($template, Permissions::FULL_ACCESS_TEMPLATES, true),
                    'permissions'  => json_encode(Permissions::forTemplate($template, $families)),
                    'is_active'    => true,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);

                if ($template === 'owner') {
                    DB::table('workspace_members')->insert([
                        'workspace_id' => $workspace->id,
                        'user_id'      => $workspace->user_id,
                        'role_id'      => $roleId,
                        'status'       => 'active',
                        'created_at'   => $now,
                        'updated_at'   => $now,
                    ]);
                }
            }
        }

        foreach (self::MENU_PERMISSIONS as $route => $permission) {
            DB::table('menu_items')->where('route', $route)->whereNull('permission_key')->update(['permission_key' => $permission]);
        }

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->whereIn('route', array_keys(self::MENU_PERMISSIONS))->whereIn('permission_key', array_values(self::MENU_PERMISSIONS))->update(['permission_key' => null]);

        Schema::dropIfExists('workspace_members');
        Schema::dropIfExists('workspace_roles');

        CatalogVersion::bump();
    }
};
