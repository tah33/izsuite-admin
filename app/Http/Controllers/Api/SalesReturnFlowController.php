<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Sales\ReturnStepRequest;
use App\Http\Resources\Sales\SalesReturnResource;
use App\Services\Api\Sales\SalesReturnService;
use Illuminate\Http\JsonResponse;

/** A customer return's steps (Doc 16 Phase E): submit, authorise, reject, receive, complete and cancel. */
class SalesReturnFlowController extends Controller
{
    public function __construct(
        protected SalesReturnService $salesReturnService,
    ) {}

    public function submit(ReturnStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->salesReturnService->submit($this->currentWorkspace($request), $id, $request->user(), $request->validated('number')), 'submitted');
    }

    public function authorise(ReturnStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->salesReturnService->authorise($this->currentWorkspace($request), $id, $request->user()), 'authorised');
    }

    public function reject(ReturnStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->salesReturnService->reject($this->currentWorkspace($request), $id, $request->user(), $request->validated('reason')), 'rejected');
    }

    public function receive(ReturnStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->salesReturnService->receive($this->currentWorkspace($request), $id, $request->user(), $request->validated('received_on')), 'received');
    }

    public function complete(ReturnStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->salesReturnService->complete($this->currentWorkspace($request), $id, $request->user(), $request->validated('resolution')), 'completed');
    }

    public function cancel(ReturnStepRequest $request, int $id): JsonResponse
    {
        return $this->step(fn () => $this->salesReturnService->cancel($this->currentWorkspace($request), $id, $request->user(), $request->validated('reason')), 'cancelled');
    }

    /** "Return RET-00001 is authorised." */
    private function step(callable $change, string $done): JsonResponse
    {
        try {
            $return = $change();

            return response()->json([
                'message' => "Return {$return->number} is {$done}.",
                'data'    => new SalesReturnResource($return),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}