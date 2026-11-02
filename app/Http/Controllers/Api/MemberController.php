<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\Members\InviteMemberRequest;
use App\Http\Requests\Api\Members\TransferOwnershipRequest;
use App\Http\Requests\Api\Members\UpdateMemberRequest;
use App\Http\Resources\Members\MemberListResource;
use App\Http\Resources\Members\MemberResource;
use App\Services\Api\Members\MemberService;
use App\Services\Api\Members\MemberStatusService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The business's users (Doc 12 sections 4 to 9): listed and exported,
 * invited, changed, their invitations sent again or cancelled, suspended,
 * reactivated, removed, signed out - and the business handed to one of them.
 */
class MemberController extends Controller
{
    public function __construct(
        protected MemberService $memberService,
        protected MemberStatusService $statusService,
    ) {}

    public function index(Request $request): JsonResponse
    {
        try {
            return response()->json([
                'data' => MemberListResource::collection($this->memberService->list($this->currentWorkspace($request))),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function export(Request $request): StreamedResponse
    {
        try {
            return $this->memberService->export($this->currentWorkspace($request));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(Request $request, int $id): JsonResponse
    {
        try {
            return response()->json([
                'data' => new MemberResource($this->memberService->show($this->currentWorkspace($request), $id)),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(InviteMemberRequest $request): JsonResponse
    {
        try {
            ['member' => $member, 'sent' => $sent] = $this->memberService->invite($this->currentWorkspace($request), $request->user(), $request->validated());

            return response()->json([
                'message' => $this->invited($member->email, $sent),
                'data'    => new MemberResource($member),
            ], 201);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(UpdateMemberRequest $request, int $id): JsonResponse
    {
        try {
            $member = $this->memberService->update($this->currentWorkspace($request), $request->user(), $id, $request->validated());

            return response()->json([
                'message' => "User \"{$member->name}\" saved.",
                'data'    => new MemberResource($member),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function resend(Request $request, int $id): JsonResponse
    {
        try {
            ['member' => $member, 'sent' => $sent] = $this->memberService->resend($this->currentWorkspace($request), $request->user(), $id);

            return response()->json([
                'message' => $this->invited($member->email, $sent),
                'data'    => new MemberListResource($member),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Cancels a pending invitation. */
    public function destroy(Request $request, int $id): JsonResponse
    {
        try {
            $this->memberService->cancel($this->currentWorkspace($request), $id);

            return response()->json([
                'message' => 'Invitation cancelled.',
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function suspend(Request $request, int $id): JsonResponse
    {
        try {
            $member = $this->statusService->suspend($this->currentWorkspace($request), $request->user(), $id);

            return response()->json([
                'message' => "User \"{$member->name}\" suspended.",
                'data'    => new MemberListResource($member),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reactivate(Request $request, int $id): JsonResponse
    {
        try {
            $member = $this->statusService->reactivate($this->currentWorkspace($request), $request->user(), $id);

            return response()->json([
                'message' => "User \"{$member->name}\" reactivated.",
                'data'    => new MemberListResource($member),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function remove(Request $request, int $id): JsonResponse
    {
        try {
            $member = $this->statusService->remove($this->currentWorkspace($request), $request->user(), $id);

            return response()->json([
                'message' => "User \"{$member->name}\" removed from the business.",
                'data'    => new MemberListResource($member),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function signOut(Request $request, int $id): JsonResponse
    {
        try {
            $ended = $this->statusService->signOut($this->currentWorkspace($request), $request->user(), $id);

            return response()->json([
                'message' => $ended === 1 ? 'Signed out of 1 session.' : "Signed out of {$ended} sessions.",
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function transfer(TransferOwnershipRequest $request, int $id): JsonResponse
    {
        try {
            $member = $this->statusService->transferOwnership($this->currentWorkspace($request), $request->user(), $id, $request->validated('password'));

            return response()->json([
                'message' => "The business now belongs to \"{$member->name}\". You are an Administrator of it.",
                'data'    => new MemberListResource($member),
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    private function invited(string $email, bool $sent): string
    {
        return $sent
            ? "Invitation sent to {$email}."
            : "The invitation to {$email} is saved, but its e-mail could not be sent. Send it again from the list.";
    }
}
