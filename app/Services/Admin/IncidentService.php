<?php

namespace App\Services\Admin;

use App\Models\Admin\Incident;
use App\Models\User\User;
use App\Repositories\Admin\IncidentRepository;
use App\Repositories\Admin\StaffRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Incidents (Doc 15 section 15.23 - Phase D3b): something wrong with a
 * service, opened by an admin, with an admin responsible, and a timeline of
 * notes - each able to move it on (open, investigating, monitoring,
 * resolved, or back) or make someone else responsible. Each step is in the
 * audit log.
 */
class IncidentService
{
    public function __construct(
        protected IncidentRepository $incidents,
        protected StaffRepository $staff,
    ) {}

    public function page(array $filters): LengthAwarePaginator
    {
        return $this->incidents->page($filters, requested_per_page());
    }

    /** @return Collection<int, Incident> the incidents not resolved yet */
    public function unresolved(): Collection
    {
        return $this->incidents->unresolved();
    }

    /** What the form offers: the incident when editing, the admins who may be responsible, and the service to start with. */
    public function form(?int $id = null, ?string $service = null): array
    {
        return [
            'incident' => $id ? $this->find($id) : null,
            'staff'    => $this->staff->active(),
            'service'  => $service,
        ];
    }

    /** An incident with its timeline, and the admins who may be made responsible. */
    public function details(int $id): array
    {
        return [
            'incident' => $this->incidents->findForPage($id) ?? abort(404),
            'staff'    => $this->staff->active(),
        ];
    }

    /** Opens an incident: open, its first note saying so - with its summary - and who is responsible. */
    public function create(array $data, User $admin): Incident
    {
        $responsible = $this->responsible($data['responsible_id'] ?? null);

        $incident = DB::transaction(function () use ($data, $admin, $responsible) {
            $incident = $this->incidents->create([...$data, 'status' => Incident::OPEN, 'responsible_id' => $responsible?->id, 'created_by' => $admin->id]);

            $this->incidents->addNote($incident, [
                'user_id'        => $admin->id,
                'body'           => filled($data['summary'] ?? null) ? $data['summary'] : 'Opened.',
                'status_to'      => Incident::OPEN,
                'responsible_to' => $responsible?->name,
            ]);

            return $incident;
        });

        ActivityLogService::record('created', "Opened incident \"{$incident->title}\" ({$incident->severity})", $incident, null, $admin);

        return $incident;
    }

    /** Changes what the incident is: its title, service, severity, summary or start. */
    public function update(int $id, array $data, User $admin): Incident
    {
        $incident = $this->incidents->update($this->find($id), $data);

        ActivityLogService::record('updated', "Changed incident \"{$incident->title}\"", $incident, null, $admin);

        return $incident;
    }

    /**
     * Adds $admin's note to incident $id's timeline, moving it to the status
     * given and making the admin given responsible, where those change.
     * Resolved, it is stamped; moved back from resolved, the stamp goes.
     */
    public function note(int $id, array $data, User $admin): Incident
    {
        $incident    = $this->find($id);
        $from        = $incident->status;
        $to          = $data['status'] ?? $from;
        $newCarer    = array_key_exists('responsible_id', $data) && (int) $data['responsible_id'] !== (int) $incident->responsible_id;
        $responsible = $newCarer ? $this->responsible($data['responsible_id']) : null;

        $changes     = ['status' => $to, 'responsible_id' => $newCarer ? $responsible?->id : $incident->responsible_id];

        if ($to !== $from && ($to === Incident::RESOLVED || $from === Incident::RESOLVED)) {
            $changes['resolved_at'] = $to === Incident::RESOLVED ? now() : null;
        }

        DB::transaction(function () use ($incident, $data, $admin, $from, $to, $newCarer, $responsible, $changes) {
            $this->incidents->update($incident, $changes);

            $this->incidents->addNote($incident, [
                'user_id'        => $admin->id,
                'body'           => $data['body'],
                'status_from'    => $to !== $from ? $from : null,
                'status_to'      => $to !== $from ? $to : null,
                'responsible_to' => $newCarer ? ($responsible?->name ?? 'Nobody') : null,
            ]);
        });

        ActivityLogService::record('updated', "Added a note to incident \"{$incident->title}\"".($to !== $from ? ': '.Incident::STATUSES[$from].' to '.Incident::STATUSES[$to] : ''), $incident, null, $admin);

        return $incident;
    }

    private function find(int $id): Incident
    {
        return $this->incidents->find($id) ?? abort(404);
    }

    /** The admin $id names - refused, on responsible_id, unless they are staff who may sign in; null for nobody. */
    private function responsible(int|string|null $id): ?User
    {
        if (blank($id)) {
            return null;
        }

        return $this->staff->active()->firstWhere('id', (int) $id)
            ?? throw ValidationException::withMessages(['responsible_id' => 'Choose an admin who may sign in.']);
    }
}
