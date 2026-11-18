<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ClientAccountActionRequest;
use App\Http\Requests\Admin\ResetClientTwoFactorRequest;
use App\Services\Admin\AccountSupportService;
use App\Services\Admin\ClientService;
use Illuminate\Http\Request;

/**
 * Support's actions on a user's account, from its page in Admin > Users (Doc
 * 15 section 15.7). None shows or sets a password.
 */
class ClientAccountController extends Controller
{
    public function __construct(
        protected ClientService $clients,
        protected AccountSupportService $support,
    ) {}

    public function resendVerification(Request $request, int $id)
    {
        try {
            $client = $this->clients->findOrFail($id);

            $this->support->resendVerification($client, $request->user());

            return $this->adminSuccess($request, "A new code to verify the email address is on its way to {$client->email}.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function sendRecoveryLink(ClientAccountActionRequest $request, int $id)
    {
        try {
            $client = $this->clients->findOrFail($id);

            $this->support->sendRecoveryLink($client, $request->user(), $request->validated('reason'));

            return $this->adminSuccess($request, "A link to choose a new password is on its way to {$client->email}.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function signOut(ClientAccountActionRequest $request, int $id)
    {
        try {
            $client = $this->clients->findOrFail($id);
            $ended  = $this->support->signOutEverywhere($client, $request->user(), $request->validated('reason'));

            return $this->adminSuccess($request, $ended === 1 ? 'One session ended.' : "{$ended} sessions ended.");

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function lock(ClientAccountActionRequest $request, int $id)
    {
        try {
            $client = $this->clients->findOrFail($id);

            $this->support->lock($client, $request->user(), $request->validated('reason'));

            return $this->adminSuccess($request, 'The account is locked, and its sessions have ended.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function unlock(ClientAccountActionRequest $request, int $id)
    {
        try {
            $client = $this->clients->findOrFail($id);

            $this->support->unlock($client, $request->user(), $request->validated('reason'));

            return $this->adminSuccess($request, 'The account is unlocked.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function resetTwoFactor(ResetClientTwoFactorRequest $request, int $id)
    {
        try {
            $client = $this->clients->findOrFail($id);

            $this->support->resetTwoFactor(
                $client,
                $request->user(),
                $request->validated('confirm_password'),
                $request->validated('reason'),
                $request->validated('identity_check'),
            );

            return $this->adminSuccess($request, 'Two-factor sign-in is off for this account, and its sessions have ended.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
