<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\Countries\CountryResource;
use App\Support\Countries;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * Every country, by name, with its calling code, the labels of its
     * address fields and its time zones - the list every country field picks
     * from (Admin > Countries keeps it).
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'data' => CountryResource::collection(Countries::all()),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
