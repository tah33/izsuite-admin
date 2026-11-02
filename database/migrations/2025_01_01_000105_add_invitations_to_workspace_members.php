<?php

use App\Services\User\Apps\CatalogVersion;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Users of a business and their invitations (Doc 12 sections 4 to 8 and 20).
 *
 * A member is now who they were invited as - name, e-mail, phone, job title,
 * language and time zone - with the locations they may use: all, or those
 * in workspace_member_locations, and the one new records start at. An
 * invitation is a secret link: only its hash is kept, with when it stops
 * working, who sent it and when. A member is pending until it is accepted,
 * then active, suspended or removed; one e-mail is invited once per business.
 *
 * The owners already there get their name and e-mail. The staff businesses
 * kept before this - contact details, no sign-in - become pending users with
 * View Only, their invitations not sent yet: the owner checks each and sends
 * it. Staff without an e-mail cannot be invited and stay as they were. The
 * sidebar's Staff becomes Users.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workspace_members', function (Blueprint $table) {
            $table->string('name', 150)->nullable()->after('role_id');
            $table->string('email')->nullable()->after('name');
            $table->string('phone', 30)->nullable()->after('email');
            $table->string('job_title', 100)->nullable()->after('phone');
            $table->string('language', 10)->nullable()->after('job_title');
            $table->string('timezone', 64)->nullable()->after('language');
            $table->string('location_access', 10)->default('all')->after('timezone');
            $table->foreignId('default_location_id')->nullable()->after('location_access')->constrained('locations')->nullOnDelete();
            $table->foreignId('invited_by')->nullable()->after('status')->constrained('users')->nullOnDelete();
            $table->string('invitation_token', 64)->nullable()->unique()->after('invited_by');
            $table->timestamp('invitation_expires_at')->nullable()->after('invitation_token');
            $table->timestamp('invited_at')->nullable()->after('invitation_expires_at');
            $table->timestamp('accepted_at')->nullable()->after('invited_at');
            $table->timestamp('suspended_at')->nullable()->after('accepted_at');
            $table->timestamp('removed_at')->nullable()->after('suspended_at');

            $table->unique(['workspace_id', 'email']);
        });

        Schema::create('workspace_member_locations', function (Blueprint $table) {
            $table->foreignId('workspace_member_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->cascadeOnDelete();

            $table->primary(['workspace_member_id', 'location_id']);
        });

        $now = now();

        foreach (DB::table('workspace_members')->join('users', 'users.id', '=', 'workspace_members.user_id')->get(['workspace_members.id', 'users.first_name', 'users.last_name', 'users.email', 'workspace_members.created_at']) as $member) {
            DB::table('workspace_members')->where('id', $member->id)->update([
                'name'        => mb_substr(trim($member->first_name.' '.$member->last_name), 0, 150),
                'email'       => mb_strtolower($member->email),
                'accepted_at' => $member->created_at,
            ]);
        }

        foreach (DB::table('user_staff')->whereNotNull('email')->where('email', '!=', '')->orderBy('id')->get() as $staff) {
            $email = mb_strtolower(trim($staff->email));
            $owner = DB::table('workspaces')->join('users', 'users.id', '=', 'workspaces.user_id')->where('workspaces.id', $staff->workspace_id)->value('users.email');
            $role  = DB::table('workspace_roles')->where('workspace_id', $staff->workspace_id)->where('template', 'view_only')->value('id');

            if (! $role || $email === mb_strtolower((string) $owner) || DB::table('workspace_members')->where('workspace_id', $staff->workspace_id)->where('email', $email)->exists()) {
                continue;
            }

            DB::table('workspace_members')->insert([
                'workspace_id'        => $staff->workspace_id,
                'role_id'             => $role,
                'name'                => mb_substr($staff->name, 0, 150),
                'email'               => $email,
                'phone'               => $staff->phone,
                'location_access'     => 'all',
                'default_location_id' => DB::table('locations')->where('workspace_id', $staff->workspace_id)->where('is_primary', true)->value('id'),
                'status'              => 'pending',
                'created_at'          => $now,
                'updated_at'          => $now,
            ]);
        }

        DB::table('menu_items')->where('route', '/staff')->where('label', 'Staff')->update(['label' => 'Users', 'icon' => 'users']);

        CatalogVersion::bump();
    }

    public function down(): void
    {
        DB::table('menu_items')->where('route', '/staff')->where('label', 'Users')->update(['label' => 'Staff', 'icon' => 'user-check']);
        DB::table('workspace_members')->whereNull('user_id')->delete();

        Schema::dropIfExists('workspace_member_locations');

        Schema::table('workspace_members', function (Blueprint $table) {
            $table->dropUnique(['workspace_id', 'email']);
            $table->dropConstrainedForeignId('default_location_id');
            $table->dropConstrainedForeignId('invited_by');
            $table->dropUnique(['invitation_token']);
            $table->dropColumn([
                'name', 'email', 'phone', 'job_title', 'language', 'timezone', 'location_access',
                'invitation_token', 'invitation_expires_at', 'invited_at', 'accepted_at', 'suspended_at', 'removed_at',
            ]);
        });

        CatalogVersion::bump();
    }
};
