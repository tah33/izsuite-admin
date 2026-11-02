<?php

namespace App\Repositories\User;

use App\Models\User\Workspace;
use App\Models\User\WorkspaceRole;
use App\Support\Permissions;
use Illuminate\Database\Eloquent\Collection;

class WorkspaceRoleRepository
{
    /**
     * Business Owner and the other templates in their order, then the roles
     * the business added by name - each with how many members hold it.
     */
    public function getFor(Workspace $workspace): Collection
    {
        $order = array_keys(Permissions::TEMPLATES);

        return $workspace->roles()->withCount('members')->get()
            ->sortBy(fn (WorkspaceRole $role) => [
                $role->isTemplate() ? (int) array_search($role->template, $order, true) : count($order),
                mb_strtolower($role->name),
            ])
            ->values();
    }

    public function findFor(Workspace $workspace, int $id): ?WorkspaceRole
    {
        return $workspace->roles()->withCount('members')->find($id);
    }

    public function ownerRoleOf(Workspace $workspace): ?WorkspaceRole
    {
        return $workspace->roles()->where('template', WorkspaceRole::OWNER)->first();
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): WorkspaceRole
    {
        return $workspace->roles()->create($data);
    }

    public function update(WorkspaceRole $role, array $data): WorkspaceRole
    {
        $role->update($data);

        return $role;
    }

    public function delete(WorkspaceRole $role): void
    {
        $role->delete();
    }

    public function nameTaken(Workspace $workspace, string $name, ?int $exceptId = null): bool
    {
        return $workspace->roles()->where('name', $name)->when($exceptId, fn ($query) => $query->whereKeyNot($exceptId))->exists();
    }

    /**
     * Each default template (Doc 12 section 11) the business does not have
     * yet, made from what $templateData gives for it. Returns how many were
     * made.
     *
     * @param  callable(string): array<string, mixed>  $templateData
     */
    public function addTemplates(Workspace $workspace, callable $templateData): int
    {
        $made = 0;

        foreach (array_keys(Permissions::TEMPLATES) as $template) {
            if ($workspace->roles()->where('template', $template)->exists()) {
                continue;
            }

            $workspace->roles()->create(['template' => $template, ...$templateData($template)]);
            $made++;
        }

        return $made;
    }
}
