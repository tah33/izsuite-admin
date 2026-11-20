<?php

namespace App\Services\Api\Pos;

use App\Models\User\PosReturn;
use App\Models\User\Workspace;
use App\Repositories\User\PosReturnRepository;
use App\Repositories\User\PosSessionRepository;
use App\Services\Api\Sales\CreditNoteRefunds;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Api\Sales\SalesDocumentService;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Support\Decimal;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Pending POS refunds (Doc 18 section 18.24 - Phase E2): a return paid back
 * by a method that waits to be confirmed - an electronic refund the cashier
 * cannot see go out. Nothing is paid out yet; the credit is on the credit
 * note.
 *
 * Whoever may confirm pending payments sees a session's - one at a location
 * they may use - and confirms each once: the customer refund it becomes,
 * out of the account it was to come from, no more than the credit note may
 * still refund. Or declines it, saying why: the credit stays on the credit
 * note. Each is logged on the credit note.
 */
class PosPendingRefunds
{
    use RefusesConflicts;

    public function __construct(
        protected PosReturnRepository $returns,
        protected PosSessionRepository $sessions,
        protected CreditNoteRefunds $refunds,
        protected SalesDocumentRules $rules,
        protected SalesDocumentService $salesDocuments,
        protected WorkspaceAccess $access,
    ) {}

    /** The session's returns whose refund waits to be confirmed. */
    public function of(Workspace $workspace, int $sessionId): Collection
    {
        $session = $this->sessions->findFor($workspace, $sessionId, $this->access->locations()) ?? abort(404, 'There is no such session.');

        return $this->returns->pendingIn($session);
    }

    public function confirm(Workspace $workspace, int $id): PosReturn
    {
        $return = $this->find($workspace, $id);
        $user   = $this->access->user();

        DB::transaction(function () use ($workspace, $return, $user): void {
            $this->stillPending($this->returns->lock($return));

            $out     = Decimal::min((string) $return->refund_due, $this->rules->refundable($return->creditNote->refresh()));
            $payment = bccomp($out, '0', 2) > 0 ? $this->refunds->refund($workspace, $return->credit_note_id, $user, [
                'payment_account_id' => $return->refund_account_id, 'payment_date' => $this->salesDocuments->today($workspace), 'amount' => $out, 'method' => $return->refund_method_type,
            ]) : null;

            $this->returns->update($return, [
                'refund_status' => PosReturn::COMPLETED, 'refunded' => $payment ? $out : '0.00', 'refund_payment_id' => $payment?->id, 'refund_due' => '0.00',
                'refund_closed_by' => $user->id, 'refund_closed_at' => now(),
            ]);
        });

        ActivityLogService::record('updated', "Confirmed the {$return->refund_method_name} refund of {$return->currency} {$return->refunded} for {$return->number}", $return->creditNote, ['source' => 'api']);

        return $return;
    }

    public function decline(Workspace $workspace, int $id, string $reason): PosReturn
    {
        $return = $this->find($workspace, $id);
        $due    = (string) $return->refund_due;

        DB::transaction(function () use ($return, $reason): void {
            $this->stillPending($this->returns->lock($return));

            $this->returns->update($return, [
                'refund_status' => PosReturn::DECLINED, 'refund_due' => '0.00', 'refund_decline_reason' => $reason,
                'refund_closed_by' => $this->access->user()->id, 'refund_closed_at' => now(),
            ]);
        });

        ActivityLogService::record('updated', "Declined the {$return->refund_method_name} refund of {$return->currency} {$due} for {$return->number}: {$reason}", $return->creditNote, ['source' => 'api']);

        return $return;
    }

    /** One of the business's returns, at a location the user may use - else as if there were none. */
    private function find(Workspace $workspace, int $id): PosReturn
    {
        return $this->returns->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such return.');
    }

    private function stillPending(PosReturn $return): void
    {
        if ($return->refund_status !== PosReturn::PENDING) {
            $this->refuse($return->refund_status === PosReturn::DECLINED
                ? "{$return->number}'s refund was declined."
                : "{$return->number}'s refund is not waiting to be confirmed.");
        }
    }
}
