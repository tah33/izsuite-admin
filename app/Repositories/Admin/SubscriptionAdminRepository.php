<?php

namespace App\Repositories\Admin;

use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\Workspace;
use App\Support\SubscriptionStatus;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Admin > Subscriptions (Doc 15 section 15.13): every business's
 * subscription to a paid plan, with its business and owner, its plan, the
 * card it is paid with if any, when it was last paid, its invoice waiting to
 * be paid, and its card's last try.
 */
class SubscriptionAdminRepository
{
    /**
     * Newest first. $filters: status (a SubscriptionStatus key), package (a
     * plan key), cycle, provider (card: a card saved; transfer: none) and
     * search (the business's name or BW- ID, or its owner's name or e-mail).
     */
    public function page(array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->withFacts(WorkspaceSubscription::query()->select('workspace_subscriptions.*'))
            ->with(['workspace:id,name,user_id,status', 'workspace.user:id,first_name,last_name,email', 'package:id,key,name,price_currency', 'scheduledPackage:id,name'])
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $this->withStatus($query, $status))
            ->when($filters['package'] ?? null, fn (Builder $query, string $key) => $query->whereHas('package', fn (Builder $package) => $package->where('key', $key)))
            ->when($filters['cycle'] ?? null, fn (Builder $query, string $cycle) => $query->where('workspace_subscriptions.cycle', $cycle))
            ->when(($filters['provider'] ?? null) === 'card', fn (Builder $query) => $query->whereExists(fn (QueryBuilder $card) => $this->card($card->selectRaw('1'))))
            ->when(($filters['provider'] ?? null) === 'transfer', fn (Builder $query) => $query->whereNotExists(fn (QueryBuilder $card) => $this->card($card->selectRaw('1'))))
            ->when($filters['search'] ?? null, function (Builder $query, string $search) {
                $like = '%'.addcslashes($search, '%_\\').'%';
                $id   = preg_match('/^(BW-?)?0*(\d+)$/i', $search, $match) ? (int) $match[2] : 0;

                $query->where(fn (Builder $query) => $query
                    ->whereHas('workspace', fn (Builder $business) => $business->where('name', 'like', $like)
                        ->orWhereHas('user', fn (Builder $owner) => $owner->where('email', 'like', $like)->orWhere('first_name', 'like', $like)->orWhere('last_name', 'like', $like)))
                    ->when($id > 0, fn (Builder $query) => $query->orWhere('workspace_subscriptions.workspace_id', $id)));
            })
            ->latest('workspace_subscriptions.id')
            ->paginate(requested_per_page($perPage))
            ->withQueryString();
    }

    /**
     * One subscription with its business, owner, plans and facts; null when
     * there is none. The business and owner come whole: the actions hand them
     * to Doc 13's billing, which reads their other columns (the plan the
     * business is on, among them).
     */
    public function findForPage(int $id): ?WorkspaceSubscription
    {
        return $this->withFacts(WorkspaceSubscription::query()->select('workspace_subscriptions.*'))
            ->with(['workspace.user', 'package', 'scheduledPackage:id,name'])
            ->find($id);
    }

    /** @return Collection<int, SubscriptionInvoice> the business's latest invoices, with each one's card tries */
    public function invoicesFor(Workspace $workspace, int $limit = 12): Collection
    {
        return $workspace->subscriptionInvoices()->with('payments')->latest('issued_on')->latest('id')->limit($limit)->get();
    }

    /** Adds last_paid_at, open_invoice_due_on, open_invoice_kind, last_try_status, card_brand and card_last4. */
    private function withFacts(Builder $query): Builder
    {
        return $query->addSelect([
            'last_paid_at'        => SubscriptionInvoice::select('paid_at')->whereColumn('subscription_invoices.workspace_id', 'workspace_subscriptions.workspace_id')->where('status', SubscriptionInvoice::PAID)->latest('paid_at')->limit(1),
            'open_invoice_due_on' => SubscriptionInvoice::select('due_on')->whereColumn('subscription_invoices.workspace_id', 'workspace_subscriptions.workspace_id')->where('status', SubscriptionInvoice::OPEN)->latest('id')->limit(1),
            'open_invoice_kind'   => SubscriptionInvoice::select('kind')->whereColumn('subscription_invoices.workspace_id', 'workspace_subscriptions.workspace_id')->where('status', SubscriptionInvoice::OPEN)->latest('id')->limit(1),
            'last_try_status'     => SubscriptionPayment::select('status')->whereColumn('subscription_payments.workspace_id', 'workspace_subscriptions.workspace_id')->latest('id')->limit(1),
            'card_brand'          => fn (QueryBuilder $card) => $this->card($card->select('brand')),
            'card_last4'          => fn (QueryBuilder $card) => $this->card($card->select('last4')),
        ]);
    }

    /** The business's saved card, as a subquery. */
    private function card(QueryBuilder $query): QueryBuilder
    {
        return $query->from('workspace_cards')->whereColumn('workspace_cards.workspace_id', 'workspace_subscriptions.workspace_id')->whereNotNull('workspace_cards.card_id')->limit(1);
    }

    /** As SubscriptionStatus::of() names them: Cancellation Scheduled, then Past Due, then Downgrade Scheduled, before plain Active. */
    private function withStatus(Builder $query, string $status): Builder
    {
        $running = fn (Builder $query) => $query->where('workspace_subscriptions.status', WorkspaceSubscription::ACTIVE)->where('cancels_at_period_end', false);

        return match ($status) {
            SubscriptionStatus::CANCELLATION_SCHEDULED => $query->where('workspace_subscriptions.status', WorkspaceSubscription::ACTIVE)->where('cancels_at_period_end', true),
            SubscriptionStatus::PAST_DUE               => $running($query)->whereExists(fn (QueryBuilder $due) => $this->pastDue($due)),
            SubscriptionStatus::DOWNGRADE_SCHEDULED    => $running($query)->whereNotExists(fn (QueryBuilder $due) => $this->pastDue($due))->whereNotNull('scheduled_package_id'),
            WorkspaceSubscription::ACTIVE              => $running($query)->whereNotExists(fn (QueryBuilder $due) => $this->pastDue($due))->whereNull('scheduled_package_id'),
            default                                    => $query->where('workspace_subscriptions.status', $status),
        };
    }

    /** Its renewal waiting for payment, and its latest card try one that did not go through. */
    private function pastDue(QueryBuilder $query): QueryBuilder
    {
        return $query->selectRaw('1')
            ->from('subscription_invoices')
            ->whereColumn('subscription_invoices.workspace_id', 'workspace_subscriptions.workspace_id')
            ->where('subscription_invoices.status', SubscriptionInvoice::OPEN)
            ->where('subscription_invoices.kind', SubscriptionInvoice::RENEWAL)
            ->whereIn(DB::raw('(select subscription_payments.status from subscription_payments where subscription_payments.workspace_id = workspace_subscriptions.workspace_id order by subscription_payments.id desc limit 1)'), SubscriptionStatus::FAILED_TRIES);
    }
}
