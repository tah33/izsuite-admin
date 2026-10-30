<?php

namespace App\Repositories\Admin;

use App\Models\User\User;
use App\Models\User\WorkspaceApp;
use App\QueryFilters\SearchFilter;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pipeline\Pipeline;

/**
 * The platform's clients: the accounts that signed up on the website, each
 * with the businesses (workspaces) it owns.
 */
class ClientRepository
{
    /** The role every self-registered account gets (see AuthService). */
    private const ROLE = 'user';

    /**
     * Newest first, each with its businesses and their plans - searched by
     * name, email or a business's name, and filtered by the key of a plan one
     * of its businesses is on.
     */
    public function paginate(string $search, ?string $package, int $perPage = 15): LengthAwarePaginator
    {
        return app(Pipeline::class)
            ->send(
                $this->clients()
                    ->select(['id', 'first_name', 'last_name', 'email', 'status', 'email_verified_at', 'last_login_at', 'created_at'])
                    ->with(['workspaces' => fn ($q) => $q->select(['id', 'user_id', 'name', 'package_id'])->orderBy('id')->with('package:id,name')])
                    ->when($package, fn ($q) => $q->whereHas('workspaces.package', fn ($p) => $p->where('key', $package)))
            )
            ->through([
                new SearchFilter($search, columns: ['first_name', 'last_name', 'email'], relations: ['workspaces' => ['name']]),
            ])
            ->thenReturn()
            ->latest()
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /** Just a client's account; anyone else is a 404. */
    public function findOrFail(int $id): User
    {
        return $this->clients()->findOrFail($id);
    }

    /**
     * A client with each business: its plan and storage limit, the apps it has
     * on, how many staff, locations, contacts and items it holds, and what its
     * files take up (storage_bytes). Anyone else is a 404.
     */
    public function findWithBusinessesOrFail(int $id): User
    {
        return $this->clients()
            ->select(['id', 'first_name', 'last_name', 'username', 'email', 'phone', 'status', 'timezone', 'currency', 'email_verified_at', 'last_login_at', 'created_at'])
            ->with(['workspaces' => fn ($q) => $q
                ->select(['id', 'user_id', 'name', 'package_id', 'created_at'])
                ->withCount(['staff', 'locations', 'contacts', 'items'])
                ->withSum('businessFiles as storage_bytes', 'size')
                ->orderBy('id')
                ->with([
                    'package:id,name,storage_limit_mb',
                    'workspaceApps' => fn ($q) => $q->where('status', WorkspaceApp::STATUS_ACTIVE)->with('app:id,name,edition,sort_order'),
                ])])
            ->findOrFail($id);
    }

    private function clients(): Builder
    {
        return User::query()->whereHas('role', fn (Builder $q) => $q->where('slug', self::ROLE));
    }
}
