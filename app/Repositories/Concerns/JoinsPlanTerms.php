<?php

namespace App\Repositories\Concerns;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Query\JoinClause;

/**
 * Businesses' plan terms in SQL, as Workspace::terms() has them (Doc 15
 * Phase E3b): the version of its plan a business is held to, else its
 * plan's terms as they stand. An empty limit is no limit, so a held
 * version's empty limit stays empty.
 */
trait JoinsPlanTerms
{
    /** Joins the businesses' plans ("packages") and the versions they are held to ("held"). */
    protected function joinPlanTerms(Builder $query, bool $planRequired = false): Builder
    {
        return $query
            ->{$planRequired ? 'join' : 'leftJoin'}('packages', 'packages.id', '=', 'workspaces.package_id')
            ->leftJoin('package_versions as held', fn (JoinClause $join) => $join->on('held.id', '=', 'workspaces.package_version_id')->on('held.package_id', '=', 'workspaces.package_id'));
    }

    /** A business's plan term $column (PackageVersion::TERMS) over the joins. */
    protected static function term(string $column): string
    {
        return "(case when held.id is null then packages.{$column} else held.{$column} end)";
    }
}
