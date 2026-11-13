<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListSupportSessionsRequest;
use App\Http\Requests\Admin\OpenEditSupportSessionRequest;
use App\Http\Requests\Admin\OpenSupportSessionRequest;
use App\Services\Shared\SupportSessionService;
use Illuminate\Http\Request;

/**
 * Support sessions from the admin panel (Doc 15 section 15.22), in place of
 * Login as: opening one in a business - read only, or with the edit access
 * its own permission and the customer's agreement allow - entering it in the
 * app, ending it, and the list of every session, for review.
 */
class SupportSessionController extends Controller
{
    public function __construct(
        protected SupportSessionService $sessions,
    ) {}

    public function index(ListSupportSessionsRequest $request)
    {
        try {
            return view('admin.support-sessions.index', ['sessions' => $this->sessions->page($request->validated())]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(OpenSupportSessionRequest $request, int $id)
    {
        try {
            $this->sessions->open($this->sessions->business($id), $request->user(), $request->validated(), edit: false);

            return $this->adminSuccess($request, 'The support session is open. Enter it from Support Sessions below.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function storeWithEditAccess(OpenEditSupportSessionRequest $request, int $id)
    {
        try {
            $this->sessions->open($this->sessions->business($id), $request->user(), $request->validated(), edit: true);

            return $this->adminSuccess($request, 'The support session is open, with edit access. Enter it from Support Sessions below.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Opens the business in the app, in the session, in the tab the link opened. */
    public function enter(Request $request, int $id)
    {
        try {
            return redirect()->away($this->sessions->enter($id, $request->user()));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function end(Request $request, int $id)
    {
        try {
            $this->sessions->endById($id, $request->user());

            return $this->adminSuccess($request, 'The support session has ended.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
