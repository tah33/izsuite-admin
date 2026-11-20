<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\Storefront;
use App\Models\User\StorefrontDomain;
use App\Models\User\Workspace;
use App\Repositories\User\StorefrontDomainRepository;
use App\Repositories\User\StorefrontRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\DomainDns;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A storefront's domains (Doc 19 section 19.5 - Phase A3). Every storefront
 * has its izSuite address - its code under izSuite's storefront domain - and
 * may have domains of the business's own:
 *
 * - added waiting, each with the TXT record that proves the business holds
 *   it, and one domain no other storefront of izSuite has;
 * - verified once its DNS shows that record - looked up when asked;
 * - one verified domain is the primary one, the address the others redirect
 *   to; with none, the izSuite address is;
 * - removed at any time, its orders and customers untouched.
 *
 * Seen by whoever may view the storefront; changed under Manage Domains. Each
 * change is logged. Which names izSuite answers for - the server asks before
 * it issues a certificate - is answersFor().
 */
class StorefrontDomainService
{
    use RefusesConflicts;

    public function __construct(
        protected StorefrontRepository $storefronts,
        protected StorefrontDomainRepository $domains,
        protected WorkspaceAccess $access,
        protected DomainDns $dns,
    ) {}

    /** "rahman-online.store.izsuite.com". */
    public static function addressOf(Storefront $storefront): string
    {
        return $storefront->code.'.'.self::izsuiteDomain();
    }

    /** @return array{storefront: Storefront, domains: \Illuminate\Database\Eloquent\Collection} */
    public function list(Workspace $workspace, int $storefrontId): array
    {
        $storefront = $this->storefront($workspace, $storefrontId);

        return ['storefront' => $storefront, 'domains' => $this->domains->forStorefront($storefront)];
    }

    /** $host is validated (see SaveStorefrontDomainRequest). */
    public function add(Workspace $workspace, int $storefrontId, string $host): StorefrontDomain
    {
        $storefront = $this->storefront($workspace, $storefrontId);
        $host       = rtrim(Str::lower(trim($host)), '.');

        if ($host === self::izsuiteDomain() || str_ends_with($host, '.'.self::izsuiteDomain())) {
            throw ValidationException::withMessages(['host' => 'This is under izSuite\'s own domain: the storefront has its izSuite address already.']);
        }

        if ($this->domains->hostTaken($host)) {
            throw ValidationException::withMessages(['host' => 'Another storefront has this domain.']);
        }

        $domain = $this->domains->create($storefront, ['host' => $host, 'token' => Str::random(32)]);

        ActivityLogService::record('created', "Added the domain {$host} to the storefront {$this->quoted($storefront)}", $storefront, ['source' => 'api']);

        return $domain;
    }

    /** Its DNS looked up now: verified once its TXT record is there. */
    public function verify(Workspace $workspace, int $storefrontId, int $domainId): StorefrontDomain
    {
        $storefront = $this->storefront($workspace, $storefrontId);
        $domain     = $this->domain($storefront, $domainId);

        if ($domain->isVerified()) {
            return $domain;
        }

        $found = in_array($domain->recordValue(), $this->dns->txt($domain->recordName()), true);

        $this->domains->update($domain, ['checked_at' => now(), ...($found ? ['verified_at' => now()] : [])]);

        if (! $found) {
            $this->refuse("{$domain->host} is not verified yet: its TXT record {$domain->recordName()} was not found. A DNS change can take some hours to reach everywhere - try again later.");
        }

        ActivityLogService::record('updated', "Verified the domain {$domain->host} of the storefront {$this->quoted($storefront)}", $storefront, ['source' => 'api']);

        return $domain;
    }

    /** One verified domain the primary one - none: the izSuite address. */
    public function makePrimary(Workspace $workspace, int $storefrontId, ?int $domainId): void
    {
        $storefront = $this->storefront($workspace, $storefrontId);
        $domain     = $domainId === null ? null : $this->domain($storefront, $domainId);

        if ($domain && ! $domain->isVerified()) {
            $this->refuse("Verify {$domain->host} first: only a verified domain becomes the primary one.");
        }

        $this->domains->makePrimary($storefront, $domain);

        ActivityLogService::record('updated', 'Made '.($domain?->host ?? self::addressOf($storefront))." the primary domain of the storefront {$this->quoted($storefront)}", $storefront, ['source' => 'api']);
    }

    /** Removed: the primary one's place goes back to the izSuite address. */
    public function remove(Workspace $workspace, int $storefrontId, int $domainId): StorefrontDomain
    {
        $storefront = $this->storefront($workspace, $storefrontId);
        $domain     = $this->domain($storefront, $domainId);

        $this->domains->delete($domain);

        ActivityLogService::record('deleted', "Removed the domain {$domain->host} from the storefront {$this->quoted($storefront)}", $storefront, ['source' => 'api']);

        return $domain;
    }

    /**
     * The storefront izSuite answers for $host as - a storefront's izSuite
     * address, or a verified domain - with the host it is to be reached at;
     * null for a name izSuite does not answer for.
     *
     * @return array{storefront: Storefront, primary_host: string}|null
     */
    public function answersFor(string $host): ?array
    {
        $host   = rtrim(Str::lower(trim($host)), '.');
        $suffix = '.'.self::izsuiteDomain();

        $storefront = str_ends_with($host, $suffix) && ! str_contains(Str::beforeLast($host, $suffix), '.')
            ? $this->storefronts->findByCode(Str::beforeLast($host, $suffix))
            : $this->domains->verifiedHost($host)?->storefront;

        return $storefront ? ['storefront' => $storefront, 'primary_host' => $this->domains->primaryOf($storefront)?->host ?? self::addressOf($storefront)] : null;
    }

    private function storefront(Workspace $workspace, int $id): Storefront
    {
        return $this->storefronts->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'Storefront not found.');
    }

    private function domain(Storefront $storefront, int $id): StorefrontDomain
    {
        return $this->domains->find($storefront, $id) ?? abort(404, 'There is no such domain for this storefront.');
    }

    private static function izsuiteDomain(): string
    {
        return Str::lower((string) config('ecommerce.storefront_domain'));
    }

    /** '"Main Shop" (main-shop)'. */
    private function quoted(Storefront $storefront): string
    {
        return '"'.Str::limit($storefront->name, 100).'" ('.$storefront->code.')';
    }
}
