<?php

namespace App\Services\Api\Pos;

use App\Models\User\ExpenseCategory;
use App\Models\User\PaymentAccount;
use App\Models\User\PosCashMovement;
use App\Models\User\PosSession;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\ExpenseCategoryRepository;
use App\Repositories\User\PaymentAccountRepository;
use App\Repositories\User\PosCashMovementRepository;
use App\Repositories\User\PosSessionRepository;
use App\Repositories\User\PosSettingRepository;
use App\Services\Api\Expenses\ExpenseFlow;
use App\Services\Api\Expenses\ExpensePaymentService;
use App\Services\Api\Expenses\ExpenseService;
use App\Services\Api\PaymentAccounts\AccountTransferService;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Services\User\Workspaces\WorkspaceSequence;
use App\Support\Decimal;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Cash movements of a POS session's drawer (Doc 18 section 18.26 - Phase
 * F1), recorded in the user's own open session by whoever may record them:
 * cash in, cash out, a safe drop, a bank deposit, or a float adjustment
 * either way - each an amount, the account it moves to or from, and why.
 *
 * The drawer's cash is its register's cash account (else its cash method's).
 * A movement moves money between it and another active account of the
 * business in the session's currency, by a completed transfer: a safe drop
 * into a cash account (the safe), a bank deposit into a bank one. No more
 * comes out of the drawer than it should hold. The session counts what
 * moved: its cash in and cash out, and so the cash expected.
 *
 * Above Settings → Point of Sale's limit, one waits for a manager's approval
 * - nothing moved - unless whoever records it may approve cash movements.
 * Approved, it moves; declined, with why, it never does. A session closes
 * only once none waits. Each is logged on its session.
 *
 * Phase F2: a paid out is an expense paid from the drawer, with the Expenses
 * app - to its payee, in one of the business's active expense categories,
 * at the register's location, without tax: recorded in Expenses and paid
 * from the drawer's account, which must pay expenses. It counts as cash out.
 */
class PosCashMovementService
{
    use RefusesConflicts;

    public function __construct(
        protected PosCounter $counter,
        protected PosCashMovementRepository $movements,
        protected PosSessionRepository $sessions,
        protected PosSessionTotals $totals,
        protected PosSettingRepository $settings,
        protected PaymentAccountRepository $accounts,
        protected AccountTransferService $transfers,
        protected ExpenseService $expenses,
        protected ExpenseFlow $expenseFlow,
        protected ExpensePaymentService $expensePayments,
        protected ExpenseCategoryRepository $categories,
        protected WorkspaceSequence $sequence,
        protected WorkspaceAccess $access,
        protected PosApprovals $approvals,
    ) {}

    /**
     * What recording one starts from, in the user's own open session: the
     * drawer's account, the accounts cash moves to or from, the cash the
     * drawer should hold, and the limit above which one waits.
     *
     * @return array{drawer: ?PaymentAccount, accounts: Collection, currency: string, expected: string, limit: ?string, categories: ?Collection}
     */
    public function options(Workspace $workspace): array
    {
        $session = $this->counter->selling($workspace);
        $drawer  = $this->drawer($workspace, $session);
        $limit   = $this->settings->forWorkspace($workspace)->cash_movement_limit;

        return [
            'drawer'   => $drawer,
            'accounts' => $drawer ? $this->accounts->forCashMovements($workspace, $session->currency, $drawer->id, $this->access->locations()) : collect(),
            'currency' => $session->currency,
            'expected' => $this->totals->of($session)['expected_cash'],
            'limit'    => $limit === null ? null : (string) $limit,
            // Phase F2: a paid out's categories - none while the drawer cannot pay one.
            'categories' => $drawer && $this->paysOut($workspace, $drawer) === null ? $this->categories->activeFor($workspace) : null,
        ];
    }

    /** $data is validated (see RecordPosCashMovementRequest): its kind, the way a float adjustment goes, the other account, the amount and why. The movement - moved, or waiting. */
    public function record(Workspace $workspace, array $data): PosCashMovement
    {
        $session   = $this->counter->selling($workspace);
        $register  = $session->register;
        $drawer    = $this->drawer($workspace, $session) ?? $this->refuse("{$register->name} takes no cash: there is no drawer to move cash in or out of.");
        $type      = $data['type'];
        $direction = PosCashMovement::DIRECTIONS[$type] ?? ($data['direction'] ?? null)
            ?? throw ValidationException::withMessages(['direction' => 'Say which way the float changes: into the drawer, or out of it.']);
        // Phase F2: a paid out goes to its payee, in an expense category - no other account.
        $paidOut   = $type === PosCashMovement::PAID_OUT;
        $account   = $paidOut ? null : $this->other($workspace, $session, $drawer, $type, (int) $data['account_id']);
        $category  = $paidOut ? $this->category($workspace, $drawer, (int) $data['expense_category_id']) : null;
        $amount    = Decimal::round((string) $data['amount']);
        $limit     = $this->settings->forWorkspace($workspace)->cash_movement_limit;
        $waits     = $limit !== null && bccomp($amount, (string) $limit, 2) > 0 && ! $this->access->can('pos.cash.approve');
        // Phase I2: approved at the register by a manager, it moves now - and names them.
        $approver  = $waits ? $this->approvals->approverFor($workspace, 'cash', $data['approvals']['cash'] ?? null) : null;
        $waits     = $waits && ! $approver;
        $user      = $this->access->user();
        $today     = $this->counter->today($workspace);

        $movement = DB::transaction(function () use ($workspace, $session, $register, $drawer, $account, $category, $type, $direction, $amount, $waits, $approver, $user, $today, $data): PosCashMovement {
            $this->stillOpen($session);

            if ($direction === PosCashMovement::OUT) {
                $this->fromDrawer($session, $amount);
            }

            $movement = $this->movements->create($workspace, [
                'number'             => 'CM-'.sprintf('%05d', $this->sequence->next($workspace->id, 'pos_cash_movement')),
                'pos_session_id'     => $session->id,
                'pos_register_id'    => $register->id,
                'location_id'        => $register->location_id,
                'type'               => $type,
                'direction'          => $direction,
                'amount'             => $amount,
                'currency'           => $session->currency,
                'drawer_account_id'  => $drawer->id,
                'payment_account_id' => $account?->id,
                'payee_name'          => $category ? trim($data['payee']) : null,
                'expense_category_id' => $category?->id,
                'reason'             => trim($data['reason']),
                'status'             => $waits ? PosCashMovement::PENDING : PosCashMovement::COMPLETED,
                'created_by'         => $user->id,
                'decided_by'         => $approver?->id,
                'decided_at'         => $approver ? now() : null,
            ]);

            if (! $waits) {
                $this->move($workspace, $user, $movement, $session, $today);
            }

            return $movement;
        });

        ActivityLogService::record('created', 'Recorded the '.$movement->describe().', '.$this->between($movement, $drawer, $account)." in the POS session {$session->number}"
            .($waits ? ', waiting for its approval' : '').": {$movement->reason}", $session, ['source' => 'api']);

        return $this->movements->loadShown($movement);
    }

    /** The session's cash movements, newest first, where its register's location may be used. */
    public function of(Workspace $workspace, int $sessionId): Collection
    {
        $session = $this->sessions->findFor($workspace, $sessionId, $this->access->locations()) ?? abort(404, 'There is no such session.');

        return $this->movements->of($session);
    }

    /** One waiting for approval, approved: it moves now, while its session is open - no more out of the drawer than it should hold. */
    public function approve(Workspace $workspace, int $id): PosCashMovement
    {
        $movement = $this->find($workspace, $id);
        $user     = $this->access->user();
        $today    = $this->counter->today($workspace);

        DB::transaction(function () use ($workspace, $movement, $user, $today): void {
            $locked  = $this->waiting($movement);
            $session = $this->stillOpen($movement->session, "Session {$movement->session->number} is closed: {$movement->number} can no longer move.");

            if ($locked->direction === PosCashMovement::OUT) {
                $this->fromDrawer($session, (string) $locked->amount);
            }

            $this->movements->update($locked, ['status' => PosCashMovement::COMPLETED, 'decided_by' => $user->id, 'decided_at' => now()]);
            $this->move($workspace, $user, $locked, $session, $today);
        });

        ActivityLogService::record('updated', 'Approved the '.$movement->describe()." in the POS session {$movement->session->number}", $movement->session, ['source' => 'api']);

        return $this->movements->reload($movement);
    }

    /** One waiting for approval, declined with why: nothing moves. */
    public function decline(Workspace $workspace, int $id, string $reason): PosCashMovement
    {
        $movement = $this->find($workspace, $id);
        $why      = trim($reason);

        DB::transaction(function () use ($movement, $why): void {
            $this->movements->update($this->waiting($movement), [
                'status' => PosCashMovement::DECLINED, 'decided_by' => $this->access->user()->id, 'decided_at' => now(), 'decline_reason' => $why,
            ]);
        });

        ActivityLogService::record('updated', 'Declined the '.$movement->describe()." in the POS session {$movement->session->number}: {$why}", $movement->session, ['source' => 'api']);

        return $this->movements->reload($movement);
    }

    /** "Safe drop CM-00001", as a message starts. */
    public function named(PosCashMovement $movement): string
    {
        return ucfirst(PosCashMovement::LABELS[$movement->type])." {$movement->number}";
    }

    /** The drawer's cash account: its register's cash account, else its cash method's - none without cash. */
    private function drawer(Workspace $workspace, PosSession $session): ?PaymentAccount
    {
        $cash = $this->counter->methods($workspace, $session->register)->first(fn (array $taken) => $taken['method']->gives_change);

        return $cash ? $this->accounts->findFor($workspace, $cash['account_id']) : null;
    }

    /** The account $id cash moves to or from: another active one, in the session's currency - the safe's a cash account, the bank's a bank one. */
    private function other(Workspace $workspace, PosSession $session, PaymentAccount $drawer, string $type, int $id): PaymentAccount
    {
        $account = $this->accounts->forCashMovements($workspace, $session->currency, $drawer->id, $this->access->locations())->firstWhere('id', $id)
            ?? throw ValidationException::withMessages(['account_id' => "Choose another of the business's active accounts in {$session->currency}: not the drawer's own, {$drawer->name}."]);
        $wanted  = PosCashMovement::ACCOUNT_TYPES[$type] ?? null;

        if ($wanted !== null && $account->type !== $wanted) {
            throw ValidationException::withMessages(['account_id' => $wanted === 'cash' ? 'A safe drop goes into a cash account: the safe.' : 'A bank deposit goes into a bank account.']);
        }

        return $account;
    }

    /** The money moved: a completed transfer between the drawer's account and the other, its reference the movement's number - or, paid out, its expense paid. */
    private function move(Workspace $workspace, User $user, PosCashMovement $movement, PosSession $session, string $today): void
    {
        if ($movement->type === PosCashMovement::PAID_OUT) {
            $this->payOut($workspace, $user, $movement, $session, $today);

            return;
        }

        $in       = $movement->direction === PosCashMovement::IN;
        $transfer = $this->transfers->create($workspace, $user, [
            'from_account_id' => $in ? $movement->payment_account_id : $movement->drawer_account_id,
            'to_account_id'   => $in ? $movement->drawer_account_id : $movement->payment_account_id,
            'transfer_date'   => $today,
            'amount'          => (string) $movement->amount,
            'reference'       => $movement->number,
            'notes'           => ucfirst(PosCashMovement::LABELS[$movement->type])." in the POS session {$session->number}: {$movement->reason}",
            'complete'        => true,
        ]);

        $this->movements->update($movement, ['account_transfer_id' => $transfer->id]);
    }

    /**
     * A paid out's expense (Phase F2): one line in its category, the amount,
     * why - recorded in Expenses at the register's location, and paid from
     * the drawer's account, its reference the movement's number.
     */
    private function payOut(Workspace $workspace, User $user, PosCashMovement $movement, PosSession $session, string $today): void
    {
        $base    = $session->currency === $workspace->base_currency;
        $expense = $this->expenses->create($workspace, $user, [
            'payee_name'    => $movement->payee_name,
            'location_id'   => $movement->location_id,
            'document_date' => $today,
            'currency'      => $session->currency,
            'exchange_rate' => $base ? '1' : $this->counter->rate($workspace, $session, $today),
            'reference'     => $movement->number,
            'notes'         => "Paid out from the drawer in the POS session {$session->number}: {$movement->reason}",
            'lines'         => [['expense_category_id' => $movement->expense_category_id, 'description' => $movement->reason, 'quantity' => '1', 'unit_price' => (string) $movement->amount, 'tax_rate_id' => null]],
        ]);

        $this->expenseFlow->record($workspace, $expense->id, $user, ['confirmed' => true]);
        $this->expensePayments->record($workspace, $expense->id, $user, [
            'payment_account_id' => $movement->drawer_account_id, 'payment_date' => $today, 'amount' => (string) $movement->amount, 'method' => 'cash', 'reference' => $movement->number,
        ]);

        $this->movements->update($movement, ['expense_id' => $expense->id]);
    }

    /** Why the drawer cannot pay an expense out - without the Expenses app, or an account that pays them - or null when it can. */
    private function paysOut(Workspace $workspace, PaymentAccount $drawer): ?string
    {
        return match (true) {
            ! WorkspaceApps::for($workspace)->has('expenses') => 'Paying an expense out of the drawer needs the Expenses app: switch it on in the Apps Center.',
            ! $drawer->allow_supplier_payments                => "{$drawer->name} does not pay expenses: allow it in Payment Accounts to pay one out of the drawer.",
            default                                          => null,
        };
    }

    /** A paid out's category: one of the business's active expense categories - while the drawer may pay one out. */
    private function category(Workspace $workspace, PaymentAccount $drawer, int $id): ExpenseCategory
    {
        if ($why = $this->paysOut($workspace, $drawer)) {
            $this->refuse($why);
        }

        return $this->categories->activeFor($workspace)->firstWhere('id', $id)
            ?? throw ValidationException::withMessages(['expense_category_id' => "Choose one of the business's active expense categories."]);
    }

    /** No more out of the drawer than it should hold now. */
    private function fromDrawer(PosSession $session, string $amount): void
    {
        $expected = $this->totals->of($session)['expected_cash'];

        if (bccomp($amount, $expected, 2) > 0) {
            throw ValidationException::withMessages(['amount' => "The drawer should hold {$session->currency} {$expected}: no more can come out of it."]);
        }
    }

    /** The session, locked - refused once it is no longer open. */
    private function stillOpen(PosSession $session, ?string $refusal = null): PosSession
    {
        $locked = $this->sessions->lock($session);

        if ($locked->status !== PosSession::OPEN) {
            $this->refuse($refusal ?? "Session {$session->number} is closed: open a session of your own to move cash.");
        }

        return $locked;
    }

    /** The movement, locked - refused unless it waits for approval. */
    private function waiting(PosCashMovement $movement): PosCashMovement
    {
        $locked = $this->movements->lock($movement);

        if ($locked->status !== PosCashMovement::PENDING) {
            $this->refuse("{$locked->number} is not waiting for approval.");
        }

        return $locked;
    }

    /** One of the business's cash movements, at a location the user may use. */
    private function find(Workspace $workspace, int $id): PosCashMovement
    {
        return $this->movements->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'There is no such cash movement.');
    }

    /** "from Front Till into Safe", or the other way - or, paid out (Phase F2), "to Window Cleaner from Front Till". */
    private function between(PosCashMovement $movement, PaymentAccount $drawer, ?PaymentAccount $account): string
    {
        if ($account === null) {
            return "to {$movement->payee_name} from {$drawer->name}";
        }

        return $movement->direction === PosCashMovement::IN ? "from {$account->name} into {$drawer->name}" : "from {$drawer->name} into {$account->name}";
    }
}
