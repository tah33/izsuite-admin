<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Apps\ListAppRequest;
use App\Http\Resources\Apps\AppResource;
use App\Services\Api\Apps\AppService;
use Illuminate\Http\JsonResponse;

class AppController extends Controller
{
    public function __construct(
        protected AppService $appService,
    ) {}

    /**
     * List active apps.
     */
    public function index(ListAppRequest $request): JsonResponse
    {
        try {
            $apps = $this->appService->list([
                'search'   => $request->query('search'),
                'category' => $request->query('category'),
                'status'   => $request->query('status'),
                'per_page' => requested_per_page(),
            ]);

            return response()->json([
                'data'       => AppResource::collection($apps->items()),
                'pagination' => slim_pagination($apps),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
