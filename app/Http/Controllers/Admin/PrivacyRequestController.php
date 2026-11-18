<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListPrivacyRequestsRequest;
use App\Http\Requests\Admin\ResolvePrivacyRequestRequest;
use App\Http\Requests\Admin\StorePrivacyRequestRequest;
use App\Http\Requests\Admin\VerifyPrivacyRequestRequest;
use App\Services\Admin\Privacy\PrivacyRequestService;
use Illuminate\Http\Request;

/**
 * Admin > Privacy Requests (Doc 15 section 15.24 - Phase G1): data exports,
 * account closures and corrections, each worked once the person asking is
 * checked. Every step is in the audit log.
 */
class PrivacyRequestController extends Controller
{
    public function __construct(
        protected PrivacyRequestService $requests,
    ) {}

    public function index(ListPrivacyRequestsRequest $request)
    {
        try {
            return view('admin.privacy-requests.index', ['requests' => $this->requests->index($request->validated())]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            return view('admin.privacy-requests.create');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StorePrivacyRequestRequest $request)
    {
        try {
            $logged = $this->requests->log($request->validated(), $request->user());

            return $this->adminSuccess($request, "Privacy request {$logged->number()} is logged. Check who is asking before working on it.", route('admin.privacy-requests.show', $logged->id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(int $id)
    {
        try {
            return view('admin.privacy-requests.show', $this->requests->page($id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** How the person asking was checked. */
    public function verify(VerifyPrivacyRequestRequest $request, int $id)
    {
        try {
            $this->requests->verify($this->requests->findOrFail($id), $request->validated('identity_check'), $request->user());

            return $this->adminSuccess($request, 'Checked: the request can be worked on.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** A correction, made: what was corrected. */
    public function complete(ResolvePrivacyRequestRequest $request, int $id)
    {
        try {
            $this->requests->complete($this->requests->findOrFail($id), $request->validated('outcome'), $request->boolean('notify'), $request->user());

            return $this->adminSuccess($request, 'The request is completed.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The account closed, with the businesses it owns. */
    public function close(Request $request, int $id)
    {
        try {
            $this->requests->closeAccount($this->requests->findOrFail($id), $request->user());

            return $this->adminSuccess($request, 'The account is closed, with the businesses it owned. Their records are kept.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function reject(ResolvePrivacyRequestRequest $request, int $id)
    {
        try {
            $this->requests->reject($this->requests->findOrFail($id), $request->validated('outcome'), $request->boolean('notify'), $request->user());

            return $this->adminSuccess($request, 'The request is rejected.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The business's data export, started in the background. */
    public function export(Request $request, int $id)
    {
        try {
            $this->requests->startExport($this->requests->findOrFail($id), $request->user());

            return $this->adminSuccess($request, 'The export is being prepared. Its link goes to the person who asked as soon as it is ready.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** The export's file, while it lasts. */
    public function download(Request $request, int $id)
    {
        try {
            $privacy = $this->requests->findOrFail($id);

            return response()->download($this->requests->exportFile($privacy, $request->user()), $privacy->number().'-export.zip');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
