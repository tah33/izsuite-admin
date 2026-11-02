<?php

use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The business an activity log entry belongs to (Doc 11 section 24), so each
 * business's Activity Log shows its own - Settings > Security & Audit.
 *
 * Entries already written take it from what they were about: the business
 * itself, or a record that belongs to one - while that business is still
 * there. Entries about nothing a business holds - a sign-in, say - or about
 * a business since deleted belong to none: they stay in the platform's own
 * log, as a business deleted later leaves its entries there.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->foreignId('workspace_id')->nullable()->after('user_id')->constrained()->nullOnDelete();
        });

        DB::table('activity_logs')
            ->where('model_type', Workspace::class)
            ->whereIn('model_id', DB::table('workspaces')->select('id'))
            ->update(['workspace_id' => DB::raw('model_id')]);

        foreach (DB::table('activity_logs')->whereNull('workspace_id')->whereNotNull('model_type')->distinct()->pluck('model_type') as $type) {
            if (! class_exists($type) || ! is_subclass_of($type, Model::class)) {
                continue;
            }

            $table = (new $type)->getTable();

            if (! Schema::hasColumn($table, 'workspace_id')) {
                continue;
            }

            DB::table('activity_logs')
                ->where('model_type', $type)
                ->whereNull('workspace_id')
                ->update(['workspace_id' => DB::raw("(select {$table}.workspace_id from {$table} inner join workspaces on workspaces.id = {$table}.workspace_id where {$table}.id = activity_logs.model_id)")]);
        }
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('workspace_id');
        });
    }
};
