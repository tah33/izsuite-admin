<?php

namespace App\Services\Admin;

use App\Models\User\User;
use App\Repositories\Admin\ClientRepository;
use App\Repositories\Billing\PackageRepository;
use App\Services\Api\Auth\LoginAsService;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;

/**
 * The platform's clients - everyone who signed up on the website - and the
 * businesses each one owns. A business is moved to another plan from its
 * owner's page, through WorkspaceService.
 */
class ClientService
{
    public function __construct(
        protected ClientRepository $clientRepository,
        protected PackageRepository $packageRepository,
        protected LoginAsService $loginAs,
    ) {}

    /** A page of accounts, by ListClientsRequest's filters. */
    public function paginate(array $filters, int $perPage = 15): LengthAwarePaginator
    {
        return $this->clientRepository->paginate($filters, $perPage);
    }

    /**
     * What a client's page shows besides the account and its businesses (Doc
     * 15 section 15.7): its recent failed sign-ins, the other accounts that
     * give its phone number, and the latest admin actions on it.
     *
     * @return array{failedSignIns: Collection, sharingPhone: Collection, activity: Collection}
     */
    public function security(User $client): array
    {
        return [
            'failedSignIns' => $this->clientRepository->recentFailedSignIns($client),
            'sharingPhone'  => $this->clientRepository->sharingPhone($client),
            'activity'      => $this->clientRepository->adminActivity($client),
        ];
    }

    /** Every plan, lowest first, for the filter and each business's plan. */
    public function packages(): Collection
    {
        return $this->packageRepository->allByRank();
    }

    /** Just the client's account. */
    public function findOrFail(int $id): User
    {
        return $this->clientRepository->findOrFail($id);
    }

    /** The client with their businesses, for their page. */
    public function findWithBusinessesOrFail(int $id): User
    {
        return $this->clientRepository->findWithBusinessesOrFail($id);
    }

    /**
     * What $client - loaded by findWithBusinessesOrFail() - has across all
     * their businesses: how many businesses, users (active or invited - each
     * business's owner among them), locations, contacts and items, and the
     * bytes their files take up.
     *
     * @return array{businesses: int, users: int, locations: int, contacts: int, items: int, storage: int}
     */
    public function totals(User $client): array
    {
        $businesses = $client->workspaces;

        return [
            'businesses' => $businesses->count(),
            'users'      => (int) $businesses->sum('users_count'),
            'locations'  => (int) $businesses->sum('locations_count'),
            'contacts'   => (int) $businesses->sum('contacts_count'),
            'items'      => (int) $businesses->sum('items_count'),
            'storage'    => (int) $businesses->sum('storage_bytes'),
        ];
    }

    /** Why the admin cannot sign in as $client now, if they cannot. */
    public function loginAsProblem(User $client): ?string
    {
        return $this->loginAs->problem($client);
    }

    /** The website link that opens $client's dashboard, signed in as them. */
    public function loginAsLink(User $client, User $admin): string
    {
        $link = $this->loginAs->link($client, $admin);

        // In the admin's audit log (Doc 15 section 15.26); the user's own log says it too, once the link is used.
        ActivityLogService::record('login_as', "Opened a Login as session for {$client->name}", $client, null, $admin);

        return $link;
    }
}
