<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Files\StorageUsage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Usage and Limits (Doc 13 sections 13.11 and 13.14): the business's package
 * and how much of its storage it uses. Users, locations and apps join it when
 * their limits do.
 */
class BusinessUsageController extends Controller
{
    public function __construct(
        protected StorageUsage $storageUsage,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        try {
            $workspace = $this->currentWorkspace($request);

            return response()->json([
                'data' => [
                    'package' => $workspace->package ? ['key' => $workspace->package->key, 'name' => $workspace->package->name] : null,
                    'storage' => $this->storageUsage->summary($workspace),
                ],
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
