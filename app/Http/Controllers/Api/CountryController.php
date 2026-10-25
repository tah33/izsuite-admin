<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Support\Countries;
use Illuminate\Http\JsonResponse;

class CountryController extends Controller
{
    /**
     * Every country, by name, with its calling code and the labels of its
     * address fields - the list every country field picks from.
     */
    public function index(): JsonResponse
    {
        try {
            return response()->json([
                'data' => Countries::all(),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
