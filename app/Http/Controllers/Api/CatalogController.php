<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Apps\CatalogResource;
use App\Services\Api\Apps\PublicCatalog;
use Illuminate\Http\JsonResponse;

/** The public catalog of apps and plans, for the website (Doc 13 sections 2 and 4). No sign-in needed. */
class CatalogController extends Controller
{
    public function __construct(
        protected PublicCatalog $catalog,
    ) {}

    public function __invoke(): JsonResponse
    {
        try {
            return response()->json(['data' => new CatalogResource($this->catalog->get())]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
