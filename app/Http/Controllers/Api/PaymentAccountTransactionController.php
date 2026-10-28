<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\PaymentAccounts\ListAccountTransactionsRequest;
use App\Http\Resources\PaymentAccounts\AccountTransactionResource;
use App\Services\Api\PaymentAccounts\PaymentAccountHistory;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A payment account's history (Doc 05 section 11): a page of it, newest first
 * with running balances, and the whole of it - filtered the same way - as a
 * CSV file.
 */
class PaymentAccountTransactionController extends Controller
{
    public function __construct(
        protected PaymentAccountHistory $paymentAccountHistory,
    ) {}

    public function index(ListAccountTransactionsRequest $request, int $id): JsonResponse
    {
        try {
            $page = $this->paymentAccountHistory->page($this->currentWorkspace($request), $id, $request->validated(), requested_per_page(25));

            return response()->json([
                'data'       => AccountTransactionResource::collection($page->items()),
                'pagination' => slim_pagination($page),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function export(ListAccountTransactionsRequest $request, int $id): StreamedResponse
    {
        try {
            return $this->paymentAccountHistory->export($this->currentWorkspace($request), $id, $request->validated());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
