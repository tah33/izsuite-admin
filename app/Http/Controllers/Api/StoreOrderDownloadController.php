<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Ecommerce\StoreDownloadResource;
use App\Services\Api\Ecommerce\StorefrontOrderDownloads;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * A delivered digital product's file downloaded by its customer (Doc 19 Phase
 * G3b): from the order's own page, by its link's key, or signed in, from
 * their account's. Each answers with a link to the file that works for a few
 * minutes, and how many more times it may be downloaded.
 */
class StoreOrderDownloadController extends Controller
{
    public function __construct(
        protected StorefrontOrderDownloads $downloads,
    ) {}

    public function byKey(Request $request, string $code, string $key, int $delivery, int $file): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreDownloadResource($this->downloads->byKey($code, $key, $delivery, $file, $this->from($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function ofAccount(Request $request, string $code, int $order, int $delivery, int $file): JsonResponse
    {
        try {
            return response()->json(['data' => new StoreDownloadResource($this->downloads->ofAccount($request->attributes->get('storeCustomer'), $order, $delivery, $file, $this->from($request)))]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Where the download came from, for its record. */
    private function from(Request $request): array
    {
        return ['ip' => $request->ip(), 'agent' => $request->userAgent()];
    }
}
