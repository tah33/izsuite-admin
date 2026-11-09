<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListSubscriptionInvoicesRequest;
use App\Services\Admin\SubscriptionInvoiceService;
use Illuminate\Http\Request;

/**
 * izSuite's subscription invoices (Doc 13 Phase C1): the list, the payment
 * confirmed when a bank transfer arrives - which issues the receipt and
 * starts the plan - an invoice cancelled, and each as a PDF.
 */
class SubscriptionInvoiceController extends Controller
{
    public function __construct(
        protected SubscriptionInvoiceService $invoiceService,
    ) {}

    public function index(ListSubscriptionInvoicesRequest $request)
    {
        try {
            $invoices = $this->invoiceService->paginate($request->validated('status'), trim((string) $request->validated('search')), requested_per_page());

            return view('admin.subscription-invoices.index', compact('invoices'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function confirm(Request $request, int $id)
    {
        try {
            $invoice = $this->invoiceService->confirm($id, $request->user());

            return $this->adminSuccess($request, "Payment for {$invoice->number} confirmed. Receipt {$invoice->receipt_number} is on its way to the business.", url()->previous());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function void(Request $request, int $id)
    {
        try {
            $invoice = $this->invoiceService->void($id);

            return $this->adminSuccess($request, "Invoice {$invoice->number} cancelled.", url()->previous());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** ?receipt=1 for its receipt. */
    public function pdf(Request $request, int $id)
    {
        try {
            [$name, $pdf] = $this->invoiceService->pdf($id, $request->boolean('receipt'));

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "inline; filename={$name}"]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
