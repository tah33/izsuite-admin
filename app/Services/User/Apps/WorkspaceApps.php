<?php

namespace App\Services\User\Apps;

use App\Enums\Edition;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use Illuminate\Support\Collection;

/**
 * One business's app rows, loaded once: which family is on at which edition,
 * and which families it has used before.
 *
 * has() is the only edition check code needs - has('sales') for either
 * edition, has('sales', Edition::Full) for Full only.
 */
class WorkspaceApps
{
    /** @var Collection<string, WorkspaceApp> keyed by family_key */
    private Collection $rows;

    public function __construct(
        public readonly Workspace $workspace,
    ) {
        $this->rows = $workspace->workspaceApps()->with('app.dependencies')->get()->keyBy('family_key');
    }

    public static function for(Workspace $workspace): self
    {
        return new self($workspace);
    }

    public function has(string $family, Edition $min = Edition::Lite): bool
    {
        $row = $this->rows->get($family);

        return $row !== null && $row->isActive() && $row->app->edition->covers($min);
    }

    /** On now, or on before - so its records exist and stay readable. */
    public function hasHistory(string $family): bool
    {
        return $this->rows->has($family);
    }

    public function row(string $family): ?WorkspaceApp
    {
        return $this->rows->get($family);
    }

    /** The edition of $family that is on, if it is. */
    public function activeEdition(string $family): ?Edition
    {
        $row = $this->rows->get($family);

        return $row?->isActive() ? $row->app->edition : null;
    }

    /** @return Collection<string, WorkspaceApp> */
    public function active(): Collection
    {
        return $this->rows->filter(fn (WorkspaceApp $row) => $row->isActive());
    }

    /** @return Collection<string, WorkspaceApp> */
    public function all(): Collection
    {
        return $this->rows;
    }
}
