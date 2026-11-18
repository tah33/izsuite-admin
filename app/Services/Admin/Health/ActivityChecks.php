<?php

namespace App\Services\Admin\Health;

use App\Models\Admin\ServiceEvent;
use App\Repositories\Admin\ServiceEventRepository;
use App\Repositories\Admin\ServiceHealthRepository;
use App\Services\Billing\Payments\CardGateway;
use App\Support\AccountAlerts;
use App\Support\ServiceStatus;
use Illuminate\Support\Str;

/**
 * What izSuite does for its businesses, as Admin > Service Health reads it
 * (Doc 15 section 15.23 - Phase D3a): e-mail sent, card payments and their
 * webhooks, imports, and sign-ins - with the services not used yet, text
 * messages and exchange-rate providers.
 */
class ActivityChecks
{
    /** Minutes an import may go without moving before it is stuck. */
    public const STALLED_MINUTES = 60;

    public function __construct(
        private ServiceHealthRepository $health,
        private ServiceEventRepository $events,
        private CardGateway $gateway,
    ) {}

    /** @return list<ServiceStatus> */
    public function all(): array
    {
        return [$this->mail(), $this->payments(), $this->imports(), $this->signIns(), $this->sms(), $this->rates()];
    }

    private function mail(): ServiceStatus
    {
        $mailer = (string) config('mail.default');
        $failed = $this->health->failedNotificationEmails(now()->subDay()) + $this->health->failedDocumentEmails(now()->subDay());
        $facts  = [
            'Sent by'                             => $mailer,
            'Notification e-mails failed, 7 days' => (string) $this->health->failedNotificationEmails(now()->subDays(7)),
            'Document e-mails failed, 7 days'     => (string) $this->health->failedDocumentEmails(now()->subDays(7)),
        ];

        return match (true) {
            in_array($mailer, ['log', 'array'], true) => new ServiceStatus('mail', ServiceStatus::WARNING, 'E-mail is written to the log, not sent.', $facts),
            $failed > 0                               => new ServiceStatus('mail', ServiceStatus::WARNING, "{$failed} ".Str::plural('e-mail', $failed).' failed in the last 24 hours.', $facts),
            default                                   => new ServiceStatus('mail', ServiceStatus::OK, 'E-mail goes out.', $facts),
        };
    }

    private function payments(): ServiceStatus
    {
        if (! $this->gateway->available()) {
            return new ServiceStatus('payments', ServiceStatus::UNUSED, 'Card payments are switched off: invoices are paid by bank transfer.');
        }

        $name    = $this->gateway->name();
        $webhook = $this->events->latest($name, ServiceEvent::WEBHOOK);
        $refused = $this->events->failures($name, ServiceEvent::WEBHOOK, now()->subDay());
        $calls   = $this->events->failures($name, ServiceEvent::CALL, now()->subDay());
        $facts   = [
            'Provider'                   => Str::ucfirst($name).($this->gateway->live() ? ', live' : ', test mode'),
            'Last webhook'               => $webhook ? $webhook->created_at->diffForHumans().($webhook->ok ? '' : ', refused') : 'None yet',
            'Webhooks refused, 24 hours' => (string) $refused,
            'Calls failed, 24 hours'     => (string) $calls,
        ];

        return $refused + $calls > 0
            ? new ServiceStatus('payments', ServiceStatus::WARNING, "In the last 24 hours, {$refused} ".Str::plural('webhook', $refused)." refused and {$calls} ".Str::plural('call', $calls).' failed.', $facts)
            : new ServiceStatus('payments', ServiceStatus::OK, Str::ucfirst($name).' takes card payments.', $facts);
    }

    private function imports(): ServiceStatus
    {
        $failed  = $this->health->failedImports(now()->subDay());
        $stalled = $this->health->stalledImports(now()->subMinutes(self::STALLED_MINUTES));
        $facts   = [
            'Failed, 7 days' => (string) $this->health->failedImports(now()->subDays(7)),
            'Stuck now'      => (string) $stalled,
            'Exports'        => 'Made as they are asked: none wait',
        ];

        return match (true) {
            $stalled > 0 => new ServiceStatus('imports', ServiceStatus::WARNING, "{$stalled} ".Str::plural('import', $stalled).($stalled === 1 ? ' has' : ' have').' not moved for an hour.', $facts),
            $failed > 0  => new ServiceStatus('imports', ServiceStatus::WARNING, "{$failed} ".Str::plural('import', $failed).' failed in the last 24 hours.', $facts),
            default      => new ServiceStatus('imports', ServiceStatus::OK, 'Imports run.', $facts),
        };
    }

    private function signIns(): ServiceStatus
    {
        $repeated = $this->health->accountsFailingSignIn(now()->subDays(AccountAlerts::DAYS), AccountAlerts::FAILED_SIGN_INS);
        $refused  = $this->health->refusedAdminActions(now()->subDay());
        $facts    = [
            'Failed account sign-ins, 24 hours'                                                 => (string) $this->health->failedAccountSignIns(now()->subDay()),
            'Failed admin sign-ins, 24 hours'                                                   => (string) $this->health->failedAdminSignIns(now()->subDay()),
            'Accounts failing '.AccountAlerts::FAILED_SIGN_INS.' times or more, '.AccountAlerts::DAYS.' days' => (string) $repeated,
            'Locked accounts'                                                                   => (string) $this->health->lockedAccounts(),
            'Admin actions refused, 24 hours'                                                   => (string) $refused,
        ];

        return match (true) {
            $repeated > 0 => new ServiceStatus('security', ServiceStatus::WARNING, "{$repeated} ".Str::plural('account', $repeated).' failed to sign in '.AccountAlerts::FAILED_SIGN_INS.' times or more: see Admin > Users.', $facts),
            $refused > 0  => new ServiceStatus('security', ServiceStatus::WARNING, "{$refused} admin ".Str::plural('action', $refused).' refused in the last 24 hours: see the Audit Log.', $facts),
            default       => new ServiceStatus('security', ServiceStatus::OK, 'Nothing unusual.', $facts),
        };
    }

    private function sms(): ServiceStatus
    {
        return new ServiceStatus('sms', ServiceStatus::UNUSED, 'izSuite sends no text messages yet.');
    }

    private function rates(): ServiceStatus
    {
        return new ServiceStatus('rates', ServiceStatus::UNUSED, 'Businesses enter their own rates: no provider is asked.');
    }
}
