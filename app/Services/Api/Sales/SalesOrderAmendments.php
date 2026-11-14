<?php

namespace App\Services\Api\Sales;

use App\Models\User\SalesDocument;
use App\Repositories\User\SalesDocumentRepository;
use Illuminate\Validation\ValidationException;

/**
 * What an amendment to a confirmed sales order may not do (Doc 16 section
 * 16.9): give it another customer or currency - cancel it and make another
 * order for that - bring a line below what was delivered or invoiced of it,
 * or take away a line that has any of that, or that another document's line
 * was made from. Lines are matched by their `id` (SalesDocumentRepository::syncLines).
 */
class SalesOrderAmendments
{
    public function __construct(
        protected SalesDocumentRepository $salesDocumentRepository,
    ) {}

    /** $data is validated (AmendSalesOrderRequest); $order has its lines. */
    public function check(SalesDocument $order, array $data): void
    {
        $errors = [];

        if ((int) ($data['contact_id'] ?? 0) !== (int) $order->contact_id) {
            $errors['contact_id'] = 'A confirmed order keeps its customer. Cancel it and make a new order for another.';
        }

        if (($data['currency'] ?? null) !== $order->currency) {
            $errors['currency'] = 'A confirmed order keeps its currency.';
        }

        $lines = $order->lines->keyBy('id');
        $kept  = [];

        foreach (array_values($data['lines'] ?? []) as $index => $row) {
            $line = isset($row['id']) ? $lines->get((int) $row['id']) : null;

            if (! $line) {
                continue;
            }

            $kept[] = $line->id;
            $floor  = bccomp((string) $line->delivered_quantity, (string) $line->invoiced_quantity, 3) >= 0 ? (string) $line->delivered_quantity : (string) $line->invoiced_quantity;

            if (bccomp((string) ($row['quantity'] ?? '0'), $floor, 3) < 0) {
                $errors["lines.{$index}.quantity"] = rtrim(rtrim($floor, '0'), '.').' of it was delivered or invoiced already.';
            }
        }

        $removed = $lines->keys()->diff($kept)->values()->all();

        if ($removed !== [] && $this->salesDocumentRepository->linesInUse($removed)) {
            $errors['lines'] = 'A line delivered, invoiced or made into another document stays. Change its quantity instead.';
        }

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }
    }
}
