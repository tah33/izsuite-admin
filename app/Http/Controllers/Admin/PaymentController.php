<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPaymentsRequest;
use App\Http\Requests\Admin\RefundPaymentRequest;
use App\Services\Admin\PaymentAdminService;

/**
 * Admin > Payments (Doc 15 section 14 - Phase C2a): every payment for
 * izSuite's invoices, the failed ones among them, and refunding one - with
 * the admin's password and a reason.
 */
class PaymentController extends Controller
{
    public function __construct(
        protected PaymentAdminService $payments,
    ) {}

    public function index(ListPaymentsRequest $request)
    {
        try {
            return view('admin.payments.index', ['payments' => $this->payments->page($request->validated())]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function refund(RefundPaymentRequest $request, int $id)
    {
        try {
            $message = $this->payments->refund(
                $id,
                $request->user(),
                (string) $request->validated('amount'),
                $request->validated('reason'),
                $request->validated('confirm_password'),
                $request->validated('transfer_reference'),
            );

            return $this->adminSuccess($request, $message);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
