<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Pos\SavePosDeviceRequest;
use App\Http\Requests\Api\Pos\TestPosDeviceRequest;
use App\Http\Resources\Pos\PosDeviceResource;
use App\Services\Api\Pos\PosDeviceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/** A register's devices (Doc 18 Phase I3): listed, added, changed, removed and tested. */
class PosDeviceController extends Controller
{
    public function __construct(
        protected PosDeviceService $devices,
    ) {}

    public function index(Request $request, int $register): JsonResponse
    {
        try {
            return response()->json(['data' => PosDeviceResource::collection($this->devices->list($this->currentWorkspace($request), $register)['devices'])]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SavePosDeviceRequest $request, int $register): JsonResponse
    {
        try {
            $device = $this->devices->create($this->currentWorkspace($request), $register, $request->validated());

            return response()->json(['message' => "{$device->name} added.", 'data' => new PosDeviceResource($device)], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SavePosDeviceRequest $request, int $register, int $device): JsonResponse
    {
        try {
            $saved = $this->devices->update($this->currentWorkspace($request), $register, $device, $request->validated());

            return response()->json(['message' => "{$saved->name} saved.", 'data' => new PosDeviceResource($saved)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function destroy(Request $request, int $register, int $device): JsonResponse
    {
        try {
            $removed = $this->devices->delete($this->currentWorkspace($request), $register, $device);

            return response()->json(['message' => "{$removed->name} removed."]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function test(TestPosDeviceRequest $request, int $register, int $device): JsonResponse
    {
        try {
            $tested = $this->devices->test($this->currentWorkspace($request), $register, $device, $request->validated('scanned'));

            return response()->json([
                'message' => $tested['read'] ? "{$tested['device']->name} read {$tested['read']}." : "{$tested['device']->name} tested.",
                'data'    => new PosDeviceResource($tested['device']),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function testPage(Request $request, int $register, int $device): Response
    {
        try {
            [$filename, $pdf] = $this->devices->testPage($this->currentWorkspace($request), $register, $device);

            return response($pdf, 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => "inline; filename=\"{$filename}\""]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
