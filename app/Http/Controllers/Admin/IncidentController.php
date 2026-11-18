<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\AddIncidentNoteRequest;
use App\Http\Requests\Admin\ListIncidentsRequest;
use App\Http\Requests\Admin\NewIncidentRequest;
use App\Http\Requests\Admin\SaveIncidentRequest;
use App\Services\Admin\IncidentService;

/** Admin > Incidents (Doc 15 section 15.23 - Phase D3b): what went wrong with a service, who is on it, and its timeline until resolved. */
class IncidentController extends Controller
{
    public function __construct(
        protected IncidentService $incidents,
    ) {}

    public function index(ListIncidentsRequest $request)
    {
        try {
            return view('admin.incidents.index', ['incidents' => $this->incidents->page($request->validated())]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function create(NewIncidentRequest $request)
    {
        try {
            return view('admin.incidents.create', $this->incidents->form(service: $request->validated('service')));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function store(SaveIncidentRequest $request)
    {
        try {
            $incident = $this->incidents->create($request->validated(), $request->user());

            return $this->adminSuccess($request, "Incident \"{$incident->title}\" is open.", route('admin.incidents.show', $incident->id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function show(int $id)
    {
        try {
            return view('admin.incidents.show', $this->incidents->details($id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function edit(int $id)
    {
        try {
            return view('admin.incidents.edit', $this->incidents->form($id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function update(SaveIncidentRequest $request, int $id)
    {
        try {
            $incident = $this->incidents->update($id, $request->validated(), $request->user());

            return $this->adminSuccess($request, 'The incident is saved.', route('admin.incidents.show', $incident->id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    public function note(AddIncidentNoteRequest $request, int $id)
    {
        try {
            $incident = $this->incidents->note($id, $request->validated(), $request->user());

            return $this->adminSuccess($request, $incident->isResolved() ? 'The note is added. The incident is resolved.' : 'The note is added.', route('admin.incidents.show', $incident->id));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
