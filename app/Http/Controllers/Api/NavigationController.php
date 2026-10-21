<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\User\Apps\NavigationBuilder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NavigationController extends Controller
{
    public function __construct(
        protected NavigationBuilder $navigation,
    ) {}

    /**
     * The signed-in user's sidebar for the business, and the state of each of
     * its app families.
     */
    public function __invoke(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => $this->navigation->for($request->attributes->get('workspace'), $request->user()),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
