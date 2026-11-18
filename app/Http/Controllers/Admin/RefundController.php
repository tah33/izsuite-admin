<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListRefundsRequest;
use App\Services\Admin\PaymentAdminService;

/** Admin > Refunds (Doc 15 section 14 - Phase C2a): every refund, with its payment, reason and where it stands. */
class RefundController extends Controller
{
    public function __construct(
        protected PaymentAdminService $payments,
    ) {}

    public function index(ListRefundsRequest $request)
    {
        try {
            return view('admin.refunds.index', ['refunds' => $this->payments->refundsPage($request->validated())]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
