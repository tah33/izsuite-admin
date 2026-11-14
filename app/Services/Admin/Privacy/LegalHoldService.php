<?php

namespace App\Services\Admin\Privacy;

use App\Models\Admin\LegalHold;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\Admin\LegalHoldRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Admin\CriticalActionService;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Legal holds (Doc 15 section 15.24 - Phase G2): "legal holds prevent
 * affected records from being permanently deleted". While a hold is in force
 * on a business, its owner cannot delete it, and nobody in it can delete its
 * records - contacts, items, documents, payments, expenses, their
 * attachments and comments, accounts, locations, rates. Changing them, and
 * voiding or cancelling documents, which keep them, go on as before; so does
 * closing the business, which keeps everything. Setup lists and access -
 * members, roles, taxes, terms - stay changeable.
 *
 * Placing one needs a reason; releasing one, which lets deletions happen
 * again, is a critical action (section 15.25): the admin's password and a
 * reason. Both are audited.
 */
class LegalHoldService
{
    /** What business users hear when a hold stops them: not why, which is izSuite's to say. */
    public const REFUSAL = 'izSuite is keeping this business\'s records for now, so they cannot be deleted. Contact izSuite support to know more.';

    /** The API's permanent deletions of a business's records, by route, which a hold refuses. */
    private const DELETIONS = [
        'api.contacts.destroy', 'api.contacts.attachments.destroy',
        'api.items.destroy', 'api.items.image.destroy', 'api.items.files.destroy',
        'api.payment-accounts.destroy', 'api.account-transfers.destroy',
        'api.sales.destroy', 'api.sales.attachments.destroy', 'api.sales.comments.destroy', 'api.sales.payments.attachments.destroy',
        'api.purchases.destroy', 'api.purchases.attachments.destroy', 'api.purchases.comments.destroy', 'api.purchases.payments.attachments.destroy',
        'api.expenses.destroy', 'api.expenses.attachments.destroy', 'api.expenses.comments.destroy', 'api.expenses.payments.attachments.destroy',
        'api.exchange-rates.destroy', 'api.locations.destroy',
    ];

    public function __construct(
        protected LegalHoldRepository $holds,
        protected WorkspaceRepository $workspaces,
        protected CriticalActionService $criticalActions,
    ) {}

    public function index(array $filters): LengthAwarePaginator
    {
        return $this->holds->paginated($filters);
    }

    public function findOrFail(int $id): LegalHold
    {
        return $this->holds->findOrFail($id);
    }

    /** The holds in force on $workspace, for its admin page. */
    public function activeFor(Workspace $workspace): Collection
    {
        return $this->holds->activeFor($workspace)->toBase();
    }

    public function isHeld(Workspace $workspace): bool
    {
        return $this->holds->isHeld($workspace->id);
    }

    /** A hold on the business $data names by its BW- ID, with its reference and reason. $data is validated (StoreLegalHoldRequest). */
    public function place(array $data, User $admin): LegalHold
    {
        $workspace = $this->workspaces->find((int) preg_replace('/\D/', '', $data['business']))
            ?? throw ValidationException::withMessages(['business' => 'No business has this ID.']);

        $hold = $this->holds->create([
            'workspace_id'  => $workspace->id,
            'business_name' => $workspace->name,
            'reference'     => ($data['reference'] ?? null) ?: null,
            'reason'        => trim($data['reason']),
            'placed_by'     => $admin->id,
            'placed_at'     => now(),
        ]);

        ActivityLogService::record('created', "Placed a legal hold on {$workspace->businessId()} \"{$workspace->name}\"".($hold->reference ? " ({$hold->reference})" : ''), $hold, ['reason' => $hold->reason], $admin);

        return $hold;
    }

    /** Lifts $hold: once none is in force, the business's records can be deleted again. */
    public function release(LegalHold $hold, array $data, User $admin): void
    {
        if (! $hold->isActive()) {
            throw ValidationException::withMessages(['hold' => 'This hold is released already.']);
        }

        $this->criticalActions->confirm($admin, $data['confirm_password'] ?? null, $data['reason'] ?? null, "release the legal hold on \"{$hold->business_name}\"");

        $this->holds->update($hold, ['released_by' => $admin->id, 'released_at' => now(), 'release_reason' => trim((string) $data['reason'])]);

        ActivityLogService::record('released', "Released the legal hold on \"{$hold->business_name}\"".($hold->reference ? " ({$hold->reference})" : ''), $hold, ['reason' => trim((string) $data['reason'])], $admin);
    }

    /** What the API answers instead when $request would delete one of $workspace's records under a hold. */
    public function refusal(Request $request, Workspace $workspace): ?JsonResponse
    {
        if (! $request->isMethod('DELETE') || ! $request->routeIs(...self::DELETIONS) || ! $this->holds->isHeld($workspace->id)) {
            return null;
        }

        return response()->json(['message' => self::REFUSAL, 'legal_hold' => true], 403);
    }
}
