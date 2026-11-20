<?php

namespace App\Repositories\User;

use App\Models\User\Storefront;
use App\Models\User\StorefrontDomain;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/** A storefront's own domains (Doc 19 Phase A3), by name. */
class StorefrontDomainRepository
{
    public function forStorefront(Storefront $storefront): Collection
    {
        return StorefrontDomain::query()->where('storefront_id', $storefront->id)->orderBy('host')->get();
    }

    public function find(Storefront $storefront, int $id): ?StorefrontDomain
    {
        return StorefrontDomain::query()->where('storefront_id', $storefront->id)->find($id);
    }

    /** Whether any storefront of izSuite has $host. */
    public function hostTaken(string $host): bool
    {
        return StorefrontDomain::query()->where('host', $host)->exists();
    }

    /** The verified domain $host, with its storefront - or null. */
    public function verifiedHost(string $host): ?StorefrontDomain
    {
        return StorefrontDomain::query()->where('host', $host)->whereNotNull('verified_at')->with('storefront')->first();
    }

    /** The storefront's primary domain, if it has one. */
    public function primaryOf(Storefront $storefront): ?StorefrontDomain
    {
        return StorefrontDomain::query()->where('storefront_id', $storefront->id)->where('is_primary', true)->first();
    }

    /** Made for the storefront, so its business and storefront are never read from $data. */
    public function create(Storefront $storefront, array $data): StorefrontDomain
    {
        $domain = new StorefrontDomain($data);
        $domain->workspace_id  = $storefront->workspace_id;
        $domain->storefront_id = $storefront->id;
        $domain->save();

        return $domain;
    }

    public function update(StorefrontDomain $domain, array $data): void
    {
        $domain->update($data);
    }

    /** $domain alone the storefront's primary one - none, for its izSuite address. */
    public function makePrimary(Storefront $storefront, ?StorefrontDomain $domain): void
    {
        DB::transaction(function () use ($storefront, $domain) {
            StorefrontDomain::query()->where('storefront_id', $storefront->id)->where('is_primary', true)->update(['is_primary' => false, 'updated_at' => now()]);

            if ($domain) {
                $domain->update(['is_primary' => true]);
            }
        });
    }

    public function delete(StorefrontDomain $domain): void
    {
        $domain->delete();
    }
}
