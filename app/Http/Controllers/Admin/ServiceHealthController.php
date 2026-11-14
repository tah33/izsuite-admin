<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\Admin\ServiceHealthService;
use Illuminate\Http\Request;

/** Admin > Service Health (Doc 15 section 15.23 - Phase D3a): the services, their failures, and failed jobs run again or forgotten. */
class ServiceHealthController extends Controller
{
    public function __construct(
        protected ServiceHealthService $health,
    ) {}

    public function index()
    {
        try {
            return view('admin.service-health.index', $this->health->report());

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function retry(Request $request, string $uuid)
    {
        try {
            $name = $this->health->retry($uuid, $request->user());

            return $this->adminSuccess($request, "{$name} is back on the queue.", route('admin.service-health.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function forget(Request $request, string $uuid)
    {
        try {
            $name = $this->health->forget($uuid, $request->user());

            return $this->adminSuccess($request, "{$name} is off the list. It was not run again.", route('admin.service-health.index'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
