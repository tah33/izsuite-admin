<?php

namespace App\Repositories\User;

use App\Models\User\CrmEmailTemplate;
use App\Models\User\Workspace;
use Illuminate\Database\Eloquent\Collection;

/** A business's CRM e-mail templates (Doc 17 Phase F3b). */
class CrmEmailTemplateRepository
{
    /** @return Collection<int, CrmEmailTemplate> by name */
    public function getFor(Workspace $workspace): Collection
    {
        return CrmEmailTemplate::query()->where('workspace_id', $workspace->id)->orderBy('name')->orderBy('id')->get();
    }

    public function findFor(Workspace $workspace, int $id): ?CrmEmailTemplate
    {
        return CrmEmailTemplate::query()->where('workspace_id', $workspace->id)->find($id);
    }

    /** Made through the business, so its workspace is never read from $data. */
    public function create(Workspace $workspace, array $data): CrmEmailTemplate
    {
        $template = new CrmEmailTemplate($data);
        $template->workspace_id = $workspace->id;
        $template->save();

        return $template;
    }

    public function update(CrmEmailTemplate $template, array $data): CrmEmailTemplate
    {
        $template->update($data);

        return $template;
    }

    public function delete(CrmEmailTemplate $template): void
    {
        $template->delete();
    }
}
