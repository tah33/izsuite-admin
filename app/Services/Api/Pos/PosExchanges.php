<?php

namespace App\Services\Api\Pos;

use App\Models\User\Contact;
use App\Models\User\PosReturn;
use App\Models\User\PosSession;
use App\Models\User\Workspace;
use App\Repositories\User\PosReturnRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Shared\WorkspaceAccess;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

/**
 * Exchanges at POS (Doc 18 section 18.25 - Phase E3): a return made as an
 * exchange keeps its credit on its credit note for the sale of the
 * replacement - for the same customer, once.
 *
 * That sale is paid by the credit first, as much as it costs; the payments
 * pay the rest. A replacement costing less leaves the difference over: it is
 * paid back in cash from the register, by whoever may process refunds -
 * never more than the returned sale was paid in money; the rest stays credit
 * on the credit note. The return and the sale stay separate, linked records.
 */
class PosExchanges
{
    use RefusesConflicts;

    public function __construct(
        protected PosReturnRepository $returns,
        protected PosCounter $counter,
        protected SalesDocumentRules $rules,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * The exchange's return $returnId paying a sale of $due to $contact: what
     * its credit pays of it, what is paid back in cash of what it leaves over,
     * and the cash it is paid back from.
     *
     * @return array{return: PosReturn, applied: string, payback: string, cash: ?array{account_id: int, name: string, type: string}}
     */
    public function usable(Workspace $workspace, PosSession $session, Contact $contact, int $returnId, string $due): array
    {
        $return = $this->returns->findFor($workspace, $returnId, $this->access->locations())
            ?? throw ValidationException::withMessages(['exchange_return_id' => 'There is no such return.']);

        match (true) {
            $return->refund_method !== PosReturn::EXCHANGE => throw ValidationException::withMessages(['exchange_return_id' => "{$return->number} was not made as an exchange."]),
            $return->exchange_order_id !== null             => $this->refuse("{$return->number}'s exchange was sold already."),
            $return->contact_id !== $contact->id            => throw ValidationException::withMessages(['contact_id' => "{$return->number}'s exchange is for its own customer: choose them again."]),
            default                                        => null,
        };

        $note     = $return->creditNote;
        $credit   = (string) $note->balance_due;
        $applied  = Decimal::min($credit, Decimal::max($due, '0.00'));
        $leftover = bcsub($credit, $applied, 2);
        // Paid back: never more than the returned sale was paid in money.
        $payback  = bccomp($leftover, '0', 2) > 0 ? Decimal::min($leftover, $this->rules->refundable($note)) : '0.00';
        $cash     = null;

        if (bccomp($payback, '0', 2) > 0) {
            abort_unless($this->access->can('pos.returns.refund'), 403, "The replacement costs less than {$return->number}'s credit: paying the difference back needs Process Refunds.");

            $taken = $this->counter->methods($workspace, $session->register)->first(fn (array $method) => $method['method']->gives_change)
                ?? $this->refuse('This register takes no cash to pay the difference back from.');
            $cash  = ['account_id' => $taken['account_id'], 'name' => $taken['method']->name, 'type' => $taken['method']->type];
        }

        return ['return' => $return, 'applied' => $applied, 'payback' => $payback, 'cash' => $cash];
    }
}
