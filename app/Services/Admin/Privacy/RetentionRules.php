<?php

namespace App\Services\Admin\Privacy;

use App\Models\Admin\ServiceEvent;
use App\Models\Shared\IdempotencyKey;
use App\Models\User\User;
use App\Repositories\Admin\SettingRepository;
use App\Services\Shared\ActivityLogService;

/**
 * Admin > Data Retention (Doc 15 section 15.24 - Phase G3): what izSuite
 * keeps, for how long, and what removes it - as the code does it today.
 * Only the rules marked enforced delete anything; everything else is kept
 * until izSuite sets its retention policy. The days a privacy request's
 * export lasts can be changed here.
 */
class RetentionRules
{
    /** The setting holding the days an export lasts. */
    public const EXPORT_DAYS = 'privacy_export_days';

    public function __construct(
        protected SettingRepository $settings,
        protected PrivacyRequestService $requests,
    ) {}

    /** @return list<array{what: string, kept: string, removed: string, enforced: bool}> */
    public function rules(): array
    {
        $days = $this->requests->exportDays();

        return [
            ['what' => 'A business\'s records', 'kept' => 'While the business exists, open or closed', 'removed' => 'Its owner deleting the business, unless a legal hold stops it', 'enforced' => false],
            ['what' => 'A business\'s activity log', 'kept' => 'With the business', 'removed' => 'The business being deleted', 'enforced' => false],
            ['what' => 'Closed accounts', 'kept' => 'Always: they sign in no more', 'removed' => 'Nothing', 'enforced' => false],
            ['what' => 'Privacy request exports', 'kept' => trans_choice(':count day after it is made|:count days after it is made', $days), 'removed' => 'privacy:purge-exports, hourly', 'enforced' => true],
            ['what' => 'Privacy requests, legal holds and consent records', 'kept' => 'Always, as history', 'removed' => 'Nothing', 'enforced' => false],
            ['what' => 'The admin audit log', 'kept' => 'Always', 'removed' => 'Nothing: it cannot be changed or deleted from the admin panel', 'enforced' => false],
            ['what' => 'Service events', 'kept' => trans_choice(':count day|:count days', ServiceEvent::DAYS), 'removed' => 'model:prune, daily', 'enforced' => true],
            ['what' => 'Imports in progress', 'kept' => 'A week after their last change', 'removed' => 'model:prune, daily', 'enforced' => true],
            ['what' => 'Keys stopping a request from running twice', 'kept' => trans_choice(':count hour|:count hours', IdempotencyKey::HOURS), 'removed' => 'model:prune, daily', 'enforced' => true],
            ['what' => 'Admin panel sessions', 'kept' => trans_choice(':count minute idle|:count minutes idle', (int) config('session.lifetime')), 'removed' => 'Ending on their own', 'enforced' => true],
            ['what' => 'Failed background jobs', 'kept' => 'Until run again or forgotten', 'removed' => 'Admin > Service Health', 'enforced' => false],
        ];
    }

    public function exportDays(): int
    {
        return $this->requests->exportDays();
    }

    /** How many days a privacy request's export lasts, from now. */
    public function setExportDays(int $days, User $admin): void
    {
        $was = $this->exportDays();

        $this->settings->set(self::EXPORT_DAYS, $days, 'privacy');

        ActivityLogService::record('updated', "Set privacy request exports to last {$days} days", null, ['changes' => [self::EXPORT_DAYS => ['from' => $was, 'to' => $days]]], $admin);
    }
}
