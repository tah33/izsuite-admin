<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A workspace: a named workspace owned by one account, tied to an app and,
 * optionally, to a staff member. These four columns are a first cut and will
 * grow with the Workspace page.
 *
 * user_id is the owner, and the workspace goes with the account.
 *
 * app_id and staff_id are optional and nulled when what they point at goes
 * away: a workspace should outlive an app being retired by an admin, or a staff
 * account being deleted - not vanish with it, and not block the delete.
 *
 * staff_id references users: staff are users with the staff role, there is no
 * separate staff table.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workspaces', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->foreignId('app_id')->nullable()->constrained('apps')->nullOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('staff_id')->nullable()->constrained('users')->nullOnDelete();

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workspaces');
    }
};
