<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListCreditsRequest;
use App\Services\Admin\PaymentAdminService;

/** Admin > Credits (Doc 15 Phase C2b): every movement of izSuite's credit to businesses. */
class CreditController extends Controller
{
    public function __construct(
        protected PaymentAdminService $payments,
    ) {}

    public function index(ListCreditsRequest $request)
    {
        try {
            return view('admin.credits.index', ['credits' => $this->payments->creditsPage($request->validated())]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
