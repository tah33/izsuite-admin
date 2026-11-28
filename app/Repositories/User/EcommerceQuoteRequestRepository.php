<?php

namespace App\Repositories\User;

use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\Storefront;
use App\Models\User\Workspace;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;

/**
 * Requests for a quotation (Doc 19 Phase H4a): one made through a storefront
 * with its products, a number checked as free, the business's a page at a
 * time at a member's locations (by their storefront's), and one of them with
 * all it holds.
 */
class EcommerceQuoteRequestRepository
{
    /**
     * Made through the storefront, so its business is never read from $data;
     * with $items, each a product's fields in the order asked.
     */
    public function create(Storefront $storefront, array $data, array $items): EcommerceQuoteRequest
    {
        $request = new EcommerceQuoteRequest(['storefront_id' => $storefront->id, ...$data]);
        $request->workspace_id = $storefront->workspace_id;
        $request->save();

        foreach (array_values($items) as $position => $item) {
            $request->items()->create([...$item, 'position' => $position]);
        }

        return $request->load('items');
    }

    /** Phase H4b: its lead, once made. */
    public function update(EcommerceQuoteRequest $request, array $data): void
    {
        $request->update($data);
    }

    public function numberTaken(Workspace $workspace, string $number): bool
    {
        return EcommerceQuoteRequest::query()->where('workspace_id', $workspace->id)->where('number', $number)->exists();
    }

    /**
     * A page of $workspace's requests at $locationIds (any, when null), the
     * newest first - narrowed by storefront, searched by number, name or
     * e-mail - each with its storefront and products.
     */
    public function paginateFor(Workspace $workspace, ?array $locationIds, array $filters, int $perPage): LengthAwarePaginator
    {
        $search = trim((string) ($filters['search'] ?? ''));

        return $this->at($workspace, $locationIds)
            ->when($filters['storefront_id'] ?? null, fn (Builder $query, int|string $id) => $query->where('storefront_id', (int) $id))
            ->when($search !== '', fn (Builder $query) => $query->where(fn (Builder $query) => $query
                ->where('number', 'like', "%{$search}%")
                ->orWhere('customer_name', 'like', "%{$search}%")
                ->orWhere('customer_email', 'like', '%'.mb_strtolower($search).'%')))
            ->with(['storefront:id,name', 'items:id,ecommerce_quote_request_id,title,quantity,position', 'quotation:id,number,location_id'])
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage);
    }

    /** $workspace's request $id at $locationIds (any, when null), with its storefront and products - or none. */
    public function findFor(Workspace $workspace, int $id, ?array $locationIds): ?EcommerceQuoteRequest
    {
        return $this->at($workspace, $locationIds)->with(['storefront:id,name', 'items', 'quotation:id,number,location_id'])->find($id);
    }

    /** The request $id, with what its e-mail shows - or none. */
    public function findForEmail(int $id): ?EcommerceQuoteRequest
    {
        return EcommerceQuoteRequest::query()->with(['storefront', 'items'])->find($id);
    }

    private function at(Workspace $workspace, ?array $locationIds): Builder
    {
        return EcommerceQuoteRequest::query()
            ->where('workspace_id', $workspace->id)
            ->when($locationIds !== null, fn (Builder $query) => $query->whereIn('storefront_id', Storefront::query()->select('id')->where('workspace_id', $workspace->id)->whereIn('location_id', $locationIds)));
    }
}
