<?php

namespace App\Services\User\Apps;

use App\Enums\Edition;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Repositories\User\WorkspaceAppRepository;
use Illuminate\Http\Request;
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
        $this->rows = app(WorkspaceAppRepository::class)->rowsByFamily($workspace);
    }

    public static function for(Workspace $workspace): self
    {
        return new self($workspace);
    }

    /**
     * Whether the business the request is for has $family on at $min or
     * above - for what a response or a page shows only with the full Sales
     * app (Doc 16 Phase B). False with no business.
     */
    public static function requestHas(string $family, Edition $min = Edition::Lite): bool
    {
        $workspace = app(Request::class)->attributes->get('workspace');

        return $workspace instanceof Workspace && self::for($workspace)->has($family, $min);
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

    /**
     * The apps on, as roles count their permissions (Permissions::keys): each
     * family, and "{family}:full" for one on its full edition, whose own
     * permissions - the full Sales app's sales orders - show only then.
     *
     * @return list<string>
     */
    public function permissionFamilies(): array
    {
        $families = $this->active()->keys()->all();

        foreach ($families as $family) {
            if ($this->has($family, Edition::Full)) {
                $families[] = "{$family}:full";
            }
        }

        return $families;
    }
}
