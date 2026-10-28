<?php

namespace App\Services\Api\Documents;

use App\Models\User\EmailSetting;
use App\Models\User\Workspace;
use App\Repositories\User\BusinessSettingRepository;
use App\Repositories\User\EmailSettingRepository;
use App\Repositories\User\WorkspaceRepository;
use App\Services\Shared\ActivityLogService;

/**
 * Settings > Email (Doc 11 sections 15 and 22): the name a business's emails
 * go out under, where replies go, and what each document's email - and the
 * payment reminder - starts with. Left empty, the sender is the business's
 * name and replies go to its owner, which the page shows as the defaults.
 */
class EmailSettingService
{
    public function __construct(
        protected EmailSettingRepository $emailSettingRepository,
        protected BusinessSettingRepository $businessSettingRepository,
        protected WorkspaceRepository $workspaceRepository,
    ) {}

    /** @return array{setting: EmailSetting, sender_name: string, reply_to: ?string} the settings and what empty ones stand for */
    public function get(Workspace $workspace): array
    {
        return [
            'setting'     => $this->emailSettingRepository->forWorkspace($workspace),
            'sender_name' => $this->senderName($workspace, null),
            'reply_to'    => $this->replyTo($workspace, null),
        ];
    }

    /** $data is validated (see SaveEmailSettingRequest). */
    public function save(Workspace $workspace, array $data): array
    {
        $this->emailSettingRepository->save($workspace, $data);

        ActivityLogService::record('updated', 'Changed the email settings', $workspace, ['source' => 'api']);

        return $this->get($workspace);
    }

    /** The name emails go out under: the one set, or the business's. */
    public function senderName(Workspace $workspace, ?EmailSetting $setting): string
    {
        return ($setting?->sender_name ?: $this->businessSettingRepository->findFor($workspace)?->business_name) ?: $workspace->name;
    }

    /** Where replies go: the address set, or the owner's. */
    public function replyTo(Workspace $workspace, ?EmailSetting $setting): ?string
    {
        return $setting?->reply_to ?: $this->workspaceRepository->ownerEmail($workspace);
    }
}
