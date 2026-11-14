<?php

namespace App\Services\Api\Sales;

use App\Enums\DocumentType;
use App\Models\User\SalesDelivery;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\SalesDeliveryRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Services\Documents\DocumentNumbers;
use App\Services\Documents\DocumentPdf;
use App\Services\Sales\SalesDeliveryRules;
use App\Services\Sales\SalesDocumentRules;
use App\Services\Sales\SalesOrderProgress;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Carbon\CarbonImmutable;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Sales orders' deliveries (Doc 16 section 16.10). A confirmed order's Create
 * Delivery makes a draft of what is chosen - never more than each line has
 * left: ordered, less what was delivered and what other open deliveries
 * hold. A draft or ready delivery changes freely within that; Ready numbers
 * it (DN-); Delivered records who received it and the day it arrived, counts
 * it on the order, and can make a backorder - a new draft of what the order
 * still has left. Services and digital products are delivered like goods:
 * marked fulfilled. No stock moves until Inventory exists.
 */
class SalesDeliveryService
{
    use RefusesConflicts;

    /** The fields a delivery's form changes. */
    private const FIELDS = ['delivery_date', 'location_id', 'carrier', 'tracking_number', 'vehicle', 'recipient_name', 'notes'];

    public function __construct(
        protected SalesDeliveryRepository $deliveries,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected SalesDocumentService $salesDocumentService,
        protected SalesDocumentRules $salesDocumentRules,
        protected SalesDeliveryRules $rules,
        protected SalesOrderProgress $progress,
        protected DocumentNumbers $documentNumbers,
        protected WorkspaceAccess $access,
        protected DocumentPdf $documentPdf,
    ) {}

    public function list(Workspace $workspace, array $filters, int $perPage): LengthAwarePaginator
    {
        return $this->deliveries->paginateFor($workspace, [...$filters, 'location_ids' => $this->access->locations()], $perPage);
    }

    public function find(Workspace $workspace, int $id): SalesDelivery
    {
        return $this->deliveries->findFor($workspace, $id) ?? abort(404, 'Delivery not found.');
    }

    /**
     * What each line of $order has left to deliver, by its id: ordered, less
     * delivered, less what open deliveries but $except hold.
     *
     * @return array<int, array{name: string, ordered: string, delivered: string, held: string, left: string}>
     */
    public function left(SalesDocument $order, ?int $except = null): array
    {
        $held = $this->deliveries->quantitiesOf($order->lines->pluck('id')->all(), [SalesDelivery::DRAFT, SalesDelivery::READY], $except);
        $rows = [];

        foreach ($order->lines as $line) {
            $left = bcsub(bcsub((string) $line->quantity, (string) $line->delivered_quantity, 3), $held[$line->id] ?? '0', 3);

            $rows[$line->id] = [
                'name'      => $line->name,
                'ordered'   => (string) $line->quantity,
                'delivered' => (string) $line->delivered_quantity,
                'held'      => bcadd($held[$line->id] ?? '0', '0', 3),
                'left'      => bccomp($left, '0', 3) > 0 ? $left : '0.000',
            ];
        }

        return $rows;
    }

    /** A draft delivery of a confirmed order's $quantities - order line id => quantity - dated today, from the order's location. */
    public function create(Workspace $workspace, int $orderId, array $quantities, User $user): SalesDelivery
    {
        $order = $this->salesDocumentService->find($workspace, DocumentType::SalesOrder, $orderId);

        if (! $this->salesDocumentRules->allows($order, 'create_delivery', $this->salesDocumentService->today($workspace))) {
            $this->refuse('Only a confirmed sales order is delivered. Resume one on hold first.');
        }

        $lines    = $this->checked($order, $quantities);
        $delivery = DB::transaction(fn () => $this->deliveries->create($workspace, [
            'sales_document_id' => $order->id,
            'delivery_date'     => $this->salesDocumentService->today($workspace),
            'location_id'       => $order->location_id,
            'created_by'        => $user->id,
            'updated_by'        => $user->id,
        ], $lines));

        ActivityLogService::record('created', 'Made a draft delivery note from '.$this->salesDocumentService->describe($order), $delivery, ['source' => 'api']);

        return $this->deliveries->loadShown($delivery);
    }

    /** A draft or ready delivery changed. $data is validated (SaveDeliveryRequest). */
    public function update(Workspace $workspace, int $id, User $user, array $data): SalesDelivery
    {
        $delivery = $this->allowed($workspace, $id, 'edit', 'A delivered or cancelled delivery can no longer be changed.');
        $lines    = $this->checked($delivery->order, collect($data['lines'])->mapWithKeys(fn (array $line) => [(int) $line['line_id'] => (string) $line['quantity']])->all(), $delivery->id);

        DB::transaction(function () use ($delivery, $data, $lines, $user) {
            $this->deliveries->update($delivery, [
                ...Arr::only($data, self::FIELDS),
                'shipping_address' => $this->address($data['shipping_address'] ?? null),
                'updated_by'       => $user->id,
            ]);
            $this->deliveries->replaceLines($delivery, $lines);
        });

        ActivityLogService::record('updated', 'Updated '.$this->describe($delivery), $delivery, ['source' => 'api']);

        return $this->deliveries->loadShown($delivery);
    }

    /** A draft made ready to go: numbered - the next number, or one typed. */
    public function ready(Workspace $workspace, int $id, User $user, ?string $number = null): SalesDelivery
    {
        $delivery = $this->allowed($workspace, $id, 'ready', 'Only a draft delivery is made ready.');

        DB::transaction(fn () => $this->deliveries->update($delivery, [
            'number'     => $this->number($workspace, $delivery, $number),
            'status'     => SalesDelivery::READY,
            'readied_at' => now(),
            'updated_by' => $user->id,
        ]));

        ActivityLogService::record('updated', 'Made '.$this->describe($delivery).' ready', $delivery, ['source' => 'api', 'status' => 'ready']);

        return $this->deliveries->loadShown($delivery);
    }

    /**
     * A delivery that arrived: who received it and the day ($data: delivered_on,
     * recipient_name, backorder, number - DeliverRequest). Its lines count on
     * the order; with `backorder`, what the order still has left becomes a new
     * draft delivery.
     */
    public function deliver(Workspace $workspace, int $id, User $user, array $data): SalesDelivery
    {
        $delivery = $this->allowed($workspace, $id, 'deliver', 'Only a draft or ready delivery is delivered.');

        // Still within what its order has left - another may have gone out since.
        $this->checked($delivery->order, $delivery->lines->mapWithKeys(fn ($line) => [$line->sales_document_line_id => (string) $line->quantity])->all(), $delivery->id);

        $backorder = DB::transaction(function () use ($workspace, $delivery, $user, $data) {
            $this->deliveries->update($delivery, [
                'number'         => $delivery->number ?? $this->number($workspace, $delivery, $data['number'] ?? null),
                'status'         => SalesDelivery::DELIVERED,
                'delivered_on'   => $data['delivered_on'] ?? $this->salesDocumentService->today($workspace),
                'recipient_name' => filled($data['recipient_name'] ?? null) ? trim($data['recipient_name']) : $delivery->recipient_name,
                'readied_at'     => $delivery->readied_at ?? now(),
                'delivered_at'   => now(),
                'updated_by'     => $user->id,
            ]);

            $order = $this->refreshOrder($delivery->order);

            return ($data['backorder'] ?? false) ? $this->backorder($workspace, $order, $delivery, $user) : null;
        });

        ActivityLogService::record('updated', 'Delivered '.$this->describe($delivery).($backorder ? ', with a backorder of what was left' : ''), $delivery, ['source' => 'api', 'status' => 'delivered']);

        return $this->deliveries->loadShown($delivery);
    }

    /** A ready delivery that will not go, with an optional reason. A draft is deleted instead. */
    public function cancel(Workspace $workspace, int $id, User $user, ?string $reason): SalesDelivery
    {
        $delivery = $this->allowed($workspace, $id, 'cancel', 'Only a ready delivery is cancelled. A draft is deleted, and a delivered one is returned instead.');

        $this->deliveries->update($delivery, ['status' => SalesDelivery::CANCELLED, 'status_reason' => $reason, 'updated_by' => $user->id]);

        ActivityLogService::record('updated', 'Cancelled '.$this->describe($delivery), $delivery, ['source' => 'api', 'status' => 'cancelled']);

        return $this->deliveries->loadShown($delivery);
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $delivery = $this->allowed($workspace, $id, 'delete', 'Only a draft delivery is deleted.');

        $this->deliveries->delete($delivery);

        ActivityLogService::record('deleted', 'Deleted '.$this->describe($delivery), $delivery, ['source' => 'api']);
    }

    /** @return array{0: string, 1: string} its delivery note's file name and PDF - in any status: a draft says DRAFT across it */
    public function pdf(Workspace $workspace, int $id): array
    {
        return $this->documentPdf->delivery($workspace, $this->find($workspace, $id));
    }

    /** $order's lines' delivered quantities counted again from its delivered deliveries, and its delivery status with them. */
    public function refreshOrder(SalesDocument $order): SalesDocument
    {
        $order     = $this->salesDocumentRepository->loadLineItems($order);
        $delivered = $this->deliveries->quantitiesOf($order->lines->pluck('id')->all(), [SalesDelivery::DELIVERED]);

        foreach ($order->lines as $line) {
            $this->salesDocumentRepository->updateLine($line, ['delivered_quantity' => $delivered[$line->id] ?? '0']);
        }

        $this->salesDocumentRepository->update($order, ['delivery_status' => $this->progress->deliveryStatus($order)]);

        return $order;
    }

    /** What the log calls it: "delivery note DN-00001", or "draft delivery note for SO-00001". */
    public function describe(SalesDelivery $delivery): string
    {
        return $delivery->number ? "delivery note {$delivery->number}" : 'draft delivery note for '.($delivery->order?->number ?? 'a sales order');
    }

    /** What the order still has left after $delivered, as a new draft - none when nothing is left. */
    private function backorder(Workspace $workspace, SalesDocument $order, SalesDelivery $delivered, User $user): ?SalesDelivery
    {
        $lines = collect($this->left($order))
            ->filter(fn (array $row) => bccomp($row['left'], '0', 3) > 0)
            ->map(fn (array $row, int $id) => ['sales_document_line_id' => $id, 'quantity' => $row['left']])
            ->values()
            ->all();

        return $lines === [] ? null : $this->deliveries->create($workspace, [
            ...$delivered->only(['sales_document_id', 'location_id', 'shipping_address', 'carrier', 'vehicle', 'notes']),
            'delivery_date' => $this->salesDocumentService->today($workspace),
            'backorder_of'  => $delivered->id,
            'created_by'    => $user->id,
            'updated_by'    => $user->id,
        ], $lines);
    }

    /**
     * $quantities - order line id => quantity - as delivery lines: those above
     * zero, each within what its line has left (other open deliveries but
     * $except held aside).
     *
     * @return list<array{sales_document_line_id: int, quantity: string}>
     */
    private function checked(SalesDocument $order, array $quantities, ?int $except = null): array
    {
        $left   = $this->left($order, $except);
        $lines  = [];
        $errors = [];

        foreach ($quantities as $id => $quantity) {
            if (! isset($left[$id]) || bccomp((string) $quantity, '0', 3) <= 0) {
                continue;
            }

            if (bccomp((string) $quantity, $left[$id]['left'], 3) > 0) {
                $errors[] = "{$left[$id]['name']}: only ".rtrim(rtrim($left[$id]['left'], '0'), '.').' left to deliver.';
            }

            $lines[] = ['sales_document_line_id' => (int) $id, 'quantity' => (string) $quantity];
        }

        if ($errors) {
            throw ValidationException::withMessages(['lines' => implode(' ', $errors)]);
        }

        if ($lines === []) {
            throw ValidationException::withMessages(['lines' => 'Choose how much of at least one line to deliver.']);
        }

        return $lines;
    }

    private function allowed(Workspace $workspace, int $id, string $action, string $refusal): SalesDelivery
    {
        $delivery = $this->find($workspace, $id);

        if (! $this->rules->allows($delivery, $action)) {
            $this->refuse($refusal);
        }

        return $delivery;
    }

    /** The number typed (checked), or the next DN- one. */
    private function number(Workspace $workspace, SalesDelivery $delivery, ?string $typed): string
    {
        $taken = fn (string $number) => $this->deliveries->numberTaken($workspace, $number);

        return $this->documentNumbers->typed(DocumentType::Delivery, $typed, $taken)
            ?? $this->documentNumbers->next($workspace, DocumentType::Delivery, CarbonImmutable::parse($delivery->delivery_date), $taken, $delivery->location_id);
    }

    /** Its own address, or none - the order's - when nothing is in it. */
    private function address(?array $address): ?array
    {
        $parts = array_filter(Arr::only((array) $address, SalesDelivery::ADDRESS_PARTS), fn ($part) => filled($part));

        return $parts ? [...array_fill_keys(SalesDelivery::ADDRESS_PARTS, null), ...array_map(fn ($part) => trim((string) $part), $parts)] : null;
    }
}
