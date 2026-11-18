<?php

namespace App\Services\Api\Crm;

use App\Models\User\CrmEmailTemplate;
use App\Models\User\Workspace;
use App\Repositories\User\CrmEmailTemplateRepository;
use App\Services\Shared\ActivityLogService;
use App\Support\SettingChanges;

/**
 * Settings → CRM's e-mail templates (Doc 17 section 17.19 - Phase F3b):
 * added, changed and deleted by whoever manages CRM settings, each logged.
 * An e-mail keeps the text it was sent with, so a template changed or
 * deleted changes no e-mail.
 */
class CrmEmailTemplateService
{
    public function __construct(
        protected CrmEmailTemplateRepository $templates,
    ) {}

    /** $data is validated (see SaveCrmEmailTemplateRequest). */
    public function create(Workspace $workspace, array $data): CrmEmailTemplate
    {
        $template = $this->templates->create($workspace, $data);

        ActivityLogService::record('created', "Added the e-mail template \"{$template->name}\"", $template, ['source' => 'api']);

        return $template;
    }

    public function update(Workspace $workspace, int $id, array $data): CrmEmailTemplate
    {
        $template = $this->find($workspace, $id);
        $template = $this->templates->update($template, $data);

        ActivityLogService::record('updated', "Updated the e-mail template \"{$template->name}\"", $template, ['source' => 'api', 'changes' => SettingChanges::of($template)]);

        return $template;
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $template = $this->find($workspace, $id);
        $this->templates->delete($template);

        ActivityLogService::record('deleted', "Deleted the e-mail template \"{$template->name}\"", $template, ['source' => 'api']);
    }

    private function find(Workspace $workspace, int $id): CrmEmailTemplate
    {
        return $this->templates->findFor($workspace, $id) ?? abort(404, 'E-mail template not found.');
    }
}
