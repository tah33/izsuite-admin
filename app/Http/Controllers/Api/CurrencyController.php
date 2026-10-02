<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Currencies\CurrencyResource;
use App\Services\Api\Currencies\CurrencyService;
use Illuminate\Http\JsonResponse;

class CurrencyController extends Controller
{
    public function __construct(
        protected CurrencyService $currencyService,
    ) {}

    /**
     * List active currencies.
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'data' => CurrencyResource::collection($this->currencyService->list()),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
