<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ListLegalHoldsRequest;
use App\Http\Requests\Admin\ReleaseLegalHoldRequest;
use App\Http\Requests\Admin\StoreLegalHoldRequest;
use App\Services\Admin\Privacy\LegalHoldService;

/** Admin > Legal Holds (Doc 15 section 15.24 - Phase G2): holds that keep a business's records from being deleted, until released. */
class LegalHoldController extends Controller
{
    public function __construct(
        protected LegalHoldService $holds,
    ) {}

    public function index(ListLegalHoldsRequest $request)
    {
        try {
            return view('admin.legal-holds.index', ['holds' => $this->holds->index($request->validated())]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create()
    {
        try {
            return view('admin.legal-holds.create');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(StoreLegalHoldRequest $request)
    {
        try {
            $hold = $this->holds->place($request->validated(), $request->user());

            return $this->adminSuccess($request, "The hold is in force: nothing of {$hold->business_name} can be deleted until it is released.", route('admin.legal-holds.show', $hold->id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(int $id)
    {
        try {
            return view('admin.legal-holds.show', ['hold' => $this->holds->findOrFail($id)]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /** Lifted, with the admin's password and a reason. */
    public function release(ReleaseLegalHoldRequest $request, int $id)
    {
        try {
            $this->holds->release($this->holds->findOrFail($id), $request->validated(), $request->user());

            return $this->adminSuccess($request, 'The hold is released.');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
