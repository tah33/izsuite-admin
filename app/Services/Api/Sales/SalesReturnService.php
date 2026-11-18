<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesDelivery;
use App\Models\User\SalesDocument;
use App\Models\User\SalesReturn;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\SalesDeliveryRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\SalesReturnRepository;
use App\Services\Documents\DocumentNumbers;
use App\Services\Sales\SalesOrderProgress;
use App\Services\Sales\SalesReturnRules;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Support\Decimal;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Customer returns (Doc 16 section 16.17) of a sales order's delivered goods,
 * from the order or one of its delivered deliveries - never more of a line
 * than was delivered, less what other returns hold (rejected and cancelled
 * ones aside). A return goes Draft, Submitted (numbered RET-), Authorised or
 * Rejected, Received and Completed, or is cancelled before it is received.
 * Once received its quantities count as the order lines' returned ones, and
 * the order's delivery status follows. A physical return refunds nothing by
 * itself: its resolution - refund, credit note, replacement, repair or none -
 * says what was done, and a credit note is made from the invoice as before.
 * Stock waits for Inventory.
 */
class SalesReturnService
{
    use RefusesConflicts;

    private const FIELDS = ['return_date', 'location_id', 'reason', 'condition', 'resolution', 'notes'];

    public function __construct(
        protected SalesReturnRepository $returns,
        protected SalesDeliveryRepository $deliveries,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesDocumentService $salesDocumentService,
        protected SalesReturnRules $rules,
        protected SalesOrderProgress $progress,
        protected DocumentNumbers $documentNumbers,
        protected WorkspaceAccess $access,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->returns->paginateFor($workspace, [...$filters, 'location_ids' => $this->access->locations()], $perPage);
    }

    /** Another business's return - or one at a location out of reach - answers like one that does not exist. */
    public function find(Workspace $workspace, int $id): SalesReturn
    {
        $return    = $this->returns->findFor($workspace, $id);
        $locations = $this->access->locations();

        if (! $return || ($locations !== null && ! in_array($return->location_id, $locations, true))) {
            abort(404, 'Return not found.');
        }

        return $return;
    }

    /**
     * Each line of $order, by its id: what was delivered and returned of it,
     * what other returns hold - but $except's - and what is left to return.
     * From $delivery, no more than that delivery took of a line either.
     *
     * @return array<int, array{name: string, delivered: string, returned: string, held: string, left: string}>
     */
    public function left(SalesDocument $order, ?int $except = null, ?SalesDelivery $delivery = null): array
    {
        $held    = $this->returns->quantitiesOf($order->lines->pluck('id')->all(), SalesReturn::HOLDING, $except);
        $carried = $delivery?->lines->mapWithKeys(fn ($line) => [$line->sales_document_line_id => (string) $line->quantity]);
        $rows    = [];

        foreach ($order->lines as $line) {
            if ($carried && ! $carried->has($line->id)) {
                continue;
            }

            $left = bcsub((string) ($carried?->get($line->id) ?? $line->delivered_quantity), $held[$line->id] ?? '0', 3);

            $rows[$line->id] = [
                'name'      => $line->name,
                'delivered' => (string) $line->delivered_quantity,
                'returned'  => (string) $line->returned_quantity,
                'held'      => bcadd($held[$line->id] ?? '0', '0', 3),
                'left'      => bccomp($left, '0', 3) > 0 ? $left : '0.000',
            ];
        }

        return $rows;
    }

    /**
     * A draft return of an order's delivered lines - from $data's delivery_id
     * when made from a delivery. $data is validated (see SaveReturnRequest).
     */
    public function create(Workspace $workspace, int $orderId, array $data, User $user): SalesReturn
    {
        $order    = $this->salesDocumentService->find($workspace, DocumentType::SalesOrder, $orderId);
        $delivery = empty($data['delivery_id']) ? null : $this->deliveries->findFor($workspace, (int) $data['delivery_id']);

        if ($delivery && ($delivery->sales_document_id !== $order->id || $delivery->status !== SalesDelivery::DELIVERED)) {
            $this->refuse('Goods come back from a delivery of this order that was delivered.');
        }

        if (bccomp(Decimal::sum($order->lines->pluck('delivered_quantity'), 3), '0', 3) <= 0) {
            $this->refuse('Nothing of this order was delivered yet, so nothing comes back.');
        }

        $lines  = $this->checked($order, $this->quantities($data), null, $delivery);
        $return = DB::transaction(fn () => $this->returns->create($workspace, [
            ...Arr::only($data, self::FIELDS),
            'sales_document_id' => $order->id,
            'sales_delivery_id' => $delivery?->id,
            'return_date'       => $data['return_date'] ?? $this->salesDocumentService->today($workspace),
            'location_id'       => $data['location_id'] ?? $order->location_id,
            'created_by'        => $user->id,
            'updated_by'        => $user->id,
        ], $lines));

        ActivityLogService::record('created', 'Made a draft return from '.($delivery ? "delivery note {$delivery->number}" : $this->salesDocumentService->describe($order)), $return, ['source' => 'api']);

        return $this->returns->loadShown($return);
    }

    /** Changed while draft or submitted. $data is validated (see SaveReturnRequest). */
    public function update(Workspace $workspace, int $id, User $user, array $data): SalesReturn
    {
        $return = $this->allowed($workspace, $id, 'edit', 'An authorised return can no longer be changed.');
        $lines  = $this->checked($return->order, $this->quantities($data), $return->id, $return->delivery);

        DB::transaction(function () use ($return, $data, $lines, $user) {
            $this->returns->update($return, [...Arr::only($data, self::FIELDS), 'updated_by' => $user->id]);
            $this->returns->replaceLines($return, $lines);
        });

        ActivityLogService::record('updated', 'Updated '.$this->describe($return), $return, ['source' => 'api']);

        return $this->returns->loadShown($return);
    }

    /** Numbered and sent on for a decision. */
    public function submit(Workspace $workspace, int $id, User $user, ?string $number = null): SalesReturn
    {
        $return = $this->allowed($workspace, $id, 'submit', 'Only a draft return is submitted.');

        $this->checked($return->order, $this->held($return), $return->id, $return->delivery);

        DB::transaction(fn () => $this->returns->update($return, [
            'number'       => $this->number($workspace, $return, $number),
            'status'       => SalesReturn::SUBMITTED,
            'submitted_at' => now(),
            'updated_by'   => $user->id,
        ]));

        return $this->logged($return, 'Submitted '.$this->describe($return), 'submitted');
    }

    public function authorise(Workspace $workspace, int $id, User $user): SalesReturn
    {
        $return = $this->allowed($workspace, $id, 'authorise', 'Only a submitted return is authorised.');

        $this->returns->update($return, ['status' => SalesReturn::AUTHORISED, 'decided_at' => now(), 'updated_by' => $user->id]);

        return $this->logged($return, 'Authorised '.$this->describe($return), 'authorised');
    }

    public function reject(Workspace $workspace, int $id, User $user, ?string $reason): SalesReturn
    {
        $return = $this->allowed($workspace, $id, 'reject', 'Only a submitted return is rejected.');

        $this->returns->update($return, ['status' => SalesReturn::REJECTED, 'status_reason' => $reason, 'decided_at' => now(), 'updated_by' => $user->id]);

        return $this->logged($return, 'Rejected '.$this->describe($return), 'rejected');
    }

    /** The goods came back: their quantities count as the order lines' returned ones. */
    public function receive(Workspace $workspace, int $id, User $user, ?string $receivedOn): SalesReturn
    {
        $return = $this->allowed($workspace, $id, 'receive', 'Only an authorised return is received.');

        DB::transaction(function () use ($workspace, $return, $user, $receivedOn) {
            $this->returns->update($return, [
                'status'      => SalesReturn::RECEIVED,
                'received_on' => $receivedOn ?? $this->salesDocumentService->today($workspace),
                'received_at' => now(),
                'updated_by'  => $user->id,
            ]);

            $this->refreshOrder($return->order);
        });

        return $this->logged($return, 'Received '.$this->describe($return), 'received');
    }

    /** Done: what was done about it - its resolution - recorded. */
    public function complete(Workspace $workspace, int $id, User $user, string $resolution): SalesReturn
    {
        $return = $this->allowed($workspace, $id, 'complete', 'Only a received return is completed.');

        $this->returns->update($return, ['status' => SalesReturn::COMPLETED, 'resolution' => $resolution, 'completed_at' => now(), 'updated_by' => $user->id]);

        return $this->logged($return, 'Completed '.$this->describe($return), 'completed');
    }

    public function cancel(Workspace $workspace, int $id, User $user, ?string $reason): SalesReturn
    {
        $return = $this->allowed($workspace, $id, 'cancel', 'Only a submitted or authorised return is cancelled. A draft is deleted, and received goods are completed instead.');

        $this->returns->update($return, ['status' => SalesReturn::CANCELLED, 'status_reason' => $reason, 'updated_by' => $user->id]);

        return $this->logged($return, 'Cancelled '.$this->describe($return), 'cancelled');
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $return = $this->allowed($workspace, $id, 'delete', 'Only a draft return is deleted.');

        $this->returns->delete($return);

        ActivityLogService::record('deleted', 'Deleted '.$this->describe($return), $return, ['source' => 'api']);
    }

    /** The order lines' returned quantities, counted again from its received and completed returns, and its delivery status. */
    public function refreshOrder(SalesDocument $order): SalesDocument
    {
        $order    = $this->salesDocumentRepository->loadLineItems($order);
        $returned = $this->returns->quantitiesOf($order->lines->pluck('id')->all(), SalesReturn::BACK);

        foreach ($order->lines as $line) {
            $this->salesDocumentRepository->updateLine($line, ['returned_quantity' => $returned[$line->id] ?? '0']);
        }

        $this->salesDocumentRepository->update($order, ['delivery_status' => $this->progress->deliveryStatus($order)]);

        return $order;
    }

    /** What the log calls it: "return RET-00001", or "draft return for SO-00001". */
    public function describe(SalesReturn $return): string
    {
        return $return->number ? "return {$return->number}" : 'draft return for '.($return->order?->number ?? 'a sales order');
    }

    /** $data's lines as order line id => quantity. */
    private function quantities(array $data): array
    {
        return collect($data['lines'] ?? [])->mapWithKeys(fn (array $line) => [(int) $line['line_id'] => (string) $line['quantity']])->all();
    }

    /** What $return holds now, as order line id => quantity. */
    private function held(SalesReturn $return): array
    {
        return $return->lines->mapWithKeys(fn ($line) => [$line->sales_document_line_id => (string) $line->quantity])->all();
    }

    /** $quantities' lines, within what is left to return of each - the order line's warranty kept with them. */
    private function checked(SalesDocument $order, array $quantities, ?int $except, ?SalesDelivery $delivery): array
    {
        $left   = $this->left($order, $except, $delivery);
        $lines  = [];
        $errors = [];

        foreach ($quantities as $id => $quantity) {
            if (! isset($left[$id]) || bccomp((string) $quantity, '0', 3) <= 0) {
                continue;
            }

            if (bccomp((string) $quantity, $left[$id]['left'], 3) > 0) {
                $errors[] = "{$left[$id]['name']}: only ".rtrim(rtrim($left[$id]['left'], '0'), '.').' delivered and not returned yet.';
            }

            $lines[] = ['sales_document_line_id' => (int) $id, 'quantity' => (string) $quantity, 'warranty' => $order->lines->firstWhere('id', $id)?->warranty];
        }

        if ($errors) {
            throw ValidationException::withMessages(['lines' => implode(' ', $errors)]);
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Choose how much of at least one delivered line comes back.']);
        }

        return $lines;
    }

    private function allowed(Workspace $workspace, int $id, string $action, string $refusal): SalesReturn
    {
        $return = $this->find($workspace, $id);

        if (! $this->rules->allows($return, $action)) {
            $this->refuse($refusal);
        }

        return $return;
    }

    private function number(Workspace $workspace, SalesReturn $return, ?string $typed): string
    {
        $taken = fn (string $number) => $this->returns->numberTaken($workspace, $number);

        return $this->documentNumbers->typed(DocumentType::SalesReturn, $typed, $taken)
            ?? $this->documentNumbers->next($workspace, DocumentType::SalesReturn, CarbonImmutable::parse($return->return_date), $taken, $return->location_id);
    }

    private function logged(SalesReturn $return, string $what, string $status): SalesReturn
    {
        ActivityLogService::record('updated', $what, $return, ['source' => 'api', 'status' => $status]);

        return $this->returns->loadShown($return->refresh());
    }
}
