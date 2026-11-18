<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Doc 17 Phase D3b1: several pipelines (section 17.13). Each business's
 * first pipeline becomes its default - where new opportunities and
 * converted leads start. Manage Pipelines and Stages is a permission of its
 * own now (section 17.30), so the roles that manage CRM settings are given
 * it: none loses the pipelines.
 */
return new class extends Migration
{
    private const MANAGES = 'crm.settings.manage';

    private const PIPELINES = 'crm.settings.pipelines';

    public function up(): void
    {
        Schema::table('crm_pipelines', function (Blueprint $table) {
            $table->boolean('is_default')->default(false)->after('is_active');
        });

        DB::table('crm_pipelines')->orderBy('workspace_id')->orderBy('position')->orderBy('id')->get(['id', 'workspace_id'])
            ->unique('workspace_id')
            ->each(fn ($pipeline) => DB::table('crm_pipelines')->where('id', $pipeline->id)->update(['is_default' => true]));

        $this->eachRole(fn (array $permissions) => in_array(self::MANAGES, $permissions, true) && ! in_array(self::PIPELINES, $permissions, true)
            ? [...$permissions, self::PIPELINES]
            : null);
    }

    public function down(): void
    {
        $this->eachRole(fn (array $permissions) => in_array(self::PIPELINES, $permissions, true)
            ? array_values(array_diff($permissions, [self::PIPELINES]))
            : null);

        Schema::table('crm_pipelines', fn (Blueprint $table) => $table->dropColumn('is_default'));
    }

    /** Each role's permissions through $change: what it gives back is saved, null leaves the role as it is. */
    private function eachRole(callable $change): void
    {
        DB::table('workspace_roles')->whereNotNull('permissions')->orderBy('id')->each(function ($role) use ($change) {
            $changed = $change(json_decode($role->permissions, true) ?: []);

            if ($changed !== null) {
                DB::table('workspace_roles')->where('id', $role->id)->update(['permissions' => json_encode($changed)]);
            }
        });
    }
};
