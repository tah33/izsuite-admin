<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Languages\LanguageResource;
use App\Services\Api\Languages\LanguageService;
use Illuminate\Http\JsonResponse;

class LanguageController extends Controller
{
    public function __construct(
        protected LanguageService $languageService,
    ) {}

    /**
     * List active languages.
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'data' => LanguageResource::collection($this->languageService->list()),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
