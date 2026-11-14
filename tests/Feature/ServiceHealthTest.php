<?php

namespace Tests\Feature;

use App\Jobs\DeliverNotificationEmail;
use App\Mail\SubscriptionBillingMail;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Role;
use App\Models\Admin\ServiceEvent;
use App\Models\Billing\PaymentMethod;
use App\Models\Shared\ActivityLog;
use App\Models\User\Import;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\Admin\Health\Heartbeats;
use App\Services\Api\Auth\AuthService;
use App\Services\Billing\Payments\CardPaymentError;
use App\Services\Billing\Payments\StripeGateway;
use App\Support\ServiceStatus;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\SendQueuedMailable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Doc 15 Phase D3a (section 15.23): Admin > Service Health - each service
 * izSuite runs on or uses, OK, Warning, Failing or Not used, with the
 * failures behind it; a failed job run again only where it is safe, and
 * the alerts on the Overview.
 */
class ServiceHealthTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** Whether the PDF maker answers. */
    private bool $pdfUp = true;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));
        Storage::fake((string) config('files.storage'));
        Http::fake([
            '*/health'       => fn () => $this->pdfUp ? Http::response(['status' => 'up']) : Http::response('', 503),
            'api.stripe.com/*' => Http::response(['error' => ['message' => 'No such payment method']], 404),
        ]);
        config(['mail.default' => 'smtp']);
        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
    }

    private function health(?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->get(route('admin.service-health.index'))->assertOk();
    }

    /** @return array<string, ServiceStatus> the services, by key */
    private function statuses(TestResponse $page): array
    {
        return collect($page->viewData('statuses'))->keyBy('key')->all();
    }

    /** A failed job of $command, as the queue keeps one. */
    private function failedJob(string $command, string $display, string $connection = 'database'): string
    {
        $uuid = (string) Str::uuid();

        DB::table('failed_jobs')->insert([
            'uuid'       => $uuid, 'connection' => $connection, 'queue' => 'default', 'failed_at' => now(),
            'payload'    => json_encode(['uuid' => $uuid, 'displayName' => $display, 'job' => 'Illuminate\\Queue\\CallQueuedHandler@call', 'attempts' => 3, 'data' => ['commandName' => $command, 'command' => 'O:8:"stdClass":0:{}']]),
            'exception'  => "Symfony\\Component\\Mailer\\Exception\\TransportException: Connection refused\n#0 /app/vendor/...",
        ]);

        return $uuid;
    }

    public function test_a_running_platform_is_ok_and_services_not_used_say_so(): void
    {
        $this->artisan('health:heartbeat')->assertSuccessful();

        $page     = $this->health();
        $statuses = $this->statuses($page);

        $this->assertSame([], array_keys(array_filter($statuses, fn (ServiceStatus $status) => $status->alerting())));
        $this->assertSame(['payments', 'sms', 'rates'], array_values(array_map(fn (ServiceStatus $status) => $status->key, array_filter($statuses, fn (ServiceStatus $status) => $status->state === ServiceStatus::UNUSED))));
        // The heartbeat's job ran on the queue, so a worker is seen.
        $this->assertSame([ServiceStatus::OK, 'Workers are running the jobs.'], [$statuses['queue']->state, $statuses['queue']->summary]);
        $this->assertSame(['Waiting now' => '0', 'Failed in 24 hours' => '0', 'Failed jobs kept' => '0'], array_intersect_key($statuses['queue']->facts, ['Waiting now' => 0, 'Failed in 24 hours' => 0, 'Failed jobs kept' => 0]));
        $page->assertSeeInOrder(['Service Health', 'The App', 'OK', 'Scheduler', 'It runs each minute.', 'Card Payments', 'Not used', 'Failed Jobs', 'No failed jobs.']);

        $this->get(route('admin.overview'))->assertSeeInOrder(['Platform Alerts', 'None: every service is OK or not used.']);
    }

    public function test_each_failure_shows_on_its_service_and_on_the_overview(): void
    {
        // The scheduler beat seven minutes ago; no worker has run a job since.
        app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
        $this->travel(7)->minutes();
        $this->pdfUp = false;
        config(['mail.default' => 'log']);

        $business = User::factory()->create()->workspaces()->create(['name' => 'Karim Stores']);
        foreach ([Import::FAILED, Import::IMPORTING] as $status) {
            Import::create(['workspace_id' => $business->id, 'kind' => 'contacts', 'status' => $status, 'file_path' => 'imports/x.csv', 'file_name' => 'contacts.csv', 'headers' => ['Name'], 'row_count' => 3, 'error' => $status === Import::FAILED ? 'The file could not be read.' : null]);
        }
        Import::where('status', Import::IMPORTING)->toBase()->update(['updated_at' => now()->subHours(2)]);

        $someone = User::factory()->create();
        foreach (range(1, 5) as $try) {
            ActivityLog::create(['user_id' => null, 'action' => AuthService::FAILED_SIGN_IN, 'description' => 'Failed sign-in', 'model_type' => User::class, 'model_id' => $someone->id]);
        }

        PaymentMethod::where('slug', 'stripe')->firstOrFail()->update(['is_active' => true, 'is_sandbox' => true, 'credentials' => ['test' => ['publishable_key' => 'pk_test_1', 'secret_key' => 'sk_test_1', 'webhook_secret' => 'whsec_1']]]);
        $this->postJson(route('api.webhooks.stripe'), ['type' => 'checkout.session.completed'], ['Stripe-Signature' => 't=1,v1=forged'])->assertStatus(400);
        $this->assertThrows(fn () => app(StripeGateway::class)->removeCard('pm_gone'), CardPaymentError::class);

        $statuses = $this->statuses($this->health());
        $state    = fn (string $key) => [$statuses[$key]->state, $statuses[$key]->summary];

        $this->assertSame([ServiceStatus::FAILING, 'It has not run for 7 minutes: renewals, closures, usage snapshots and the workers\' heartbeat wait for it.'], $state('scheduler'));
        $this->assertSame([ServiceStatus::FAILING, 'It answered 503: PDFs may not be made.'], $state('pdf'));
        $this->assertSame([ServiceStatus::WARNING, 'E-mail is written to the log, not sent.'], $state('mail'));
        $this->assertSame([ServiceStatus::WARNING, '1 import has not moved for an hour.'], $state('imports'));
        $this->assertSame('1', $statuses['imports']->facts['Failed, 7 days']);
        $this->assertSame([ServiceStatus::WARNING, '1 account failed to sign in 5 times or more: see Admin > Users.'], $state('security'));
        $this->assertSame([ServiceStatus::WARNING, 'In the last 24 hours, 1 webhook refused and 1 call failed.'], $state('payments'));
        $this->assertSame('Stripe, test mode', $statuses['payments']->facts['Provider']);
        $this->assertStringEndsWith(' ago, refused', $statuses['payments']->facts['Last webhook']);
        $this->assertSame([[ServiceEvent::WEBHOOK, false], [ServiceEvent::CALL, false]], ServiceEvent::orderBy('id')->get()->map(fn ($event) => [$event->kind, $event->ok])->all());

        // The worker's heartbeat comes through the scheduler: with it running, no worker for ten minutes is failing.
        app(Heartbeats::class)->beat(Heartbeats::SCHEDULER);
        $this->failedJob(DeliverNotificationEmail::class, DeliverNotificationEmail::class);
        $queue = $this->statuses($this->health())['queue'];
        $this->assertSame([ServiceStatus::FAILING, 'No worker has run a job lately: e-mails, imports and new accounts\' set-up wait.'], [$queue->state, $queue->summary]);

        app(Heartbeats::class)->beat(Heartbeats::QUEUE);
        $queue = $this->statuses($this->health())['queue'];
        $this->assertSame([ServiceStatus::WARNING, '1 job failed in the last 24 hours.'], [$queue->state, $queue->summary]);

        $this->get(route('admin.overview'))->assertSeeInOrder(['Platform Alerts', 'Failing', 'PDF Maker', 'Warning', 'E-mail', 'E-mail is written to the log, not sent.']);
        $this->health()->assertSeeInOrder(['Imports That Failed', 'Karim Stores', 'contacts.csv', 'The file could not be read.', 'Card Payment Failures', 'Webhook', 'This notification is not signed by Stripe.']);
    }

    public function test_a_failed_job_runs_again_only_where_it_is_safe_and_both_are_logged(): void
    {
        $safe   = $this->failedJob(DeliverNotificationEmail::class, DeliverNotificationEmail::class);
        $unsafe = $this->failedJob(SendQueuedMailable::class, SubscriptionBillingMail::class);
        $this->health()->assertSeeInOrder(['Failed Jobs', 'SubscriptionBillingMail', 'Not run again here: the mail may have gone out.', 'DeliverNotificationEmail', 'Connection refused', 'Run Again']);

        $this->postJson(route('admin.service-health.retry', $unsafe))
            ->assertUnprocessable()->assertJsonPath('errors.job.0', 'SubscriptionBillingMail is not run again here: the mail may have gone out. Send it again from where it was sent, if it did not.');
        $this->assertTrue(DB::table('failed_jobs')->where('uuid', $unsafe)->exists());

        // Back on its own queue, its tries reset, and off the list.
        $this->post(route('admin.service-health.retry', $safe))->assertRedirect(route('admin.service-health.index'));
        $this->assertFalse(DB::table('failed_jobs')->where('uuid', $safe)->exists());
        $this->assertSame([DeliverNotificationEmail::class, 0], [json_decode(DB::table('jobs')->value('payload'), true)['data']['commandName'], json_decode(DB::table('jobs')->value('payload'), true)['attempts']]);

        $this->delete(route('admin.service-health.forget', $unsafe))->assertRedirect(route('admin.service-health.index'));
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(['Forgot the failed job SubscriptionBillingMail', 'Ran the failed job DeliverNotificationEmail again'], AdminAuditLog::where('description', 'like', '%failed job%')->latest('id')->pluck('description')->all());

        $this->post(route('admin.service-health.retry', (string) Str::uuid()))->assertNotFound();
    }

    public function test_compliance_admins_act_on_failed_jobs_others_allowed_only_look(): void
    {
        $role = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);
        $uuid = $this->failedJob(DeliverNotificationEmail::class, DeliverNotificationEmail::class);

        foreach (['billing-administrator', 'support-administrator', 'read-only-administrator'] as $slug) {
            $as = $role($slug);
            $this->health($as)->assertDontSee('Run Again');
            $this->actingAs($as)->post(route('admin.service-health.retry', $uuid))->assertForbidden();
        }

        $this->actingAs($role('product-administrator'))->get(route('admin.service-health.index'))->assertForbidden();
        $this->actingAs($role('product-administrator'))->get(route('admin.overview'))->assertOk()->assertDontSee('Platform Alerts');

        $compliance = $role('compliance-and-security-administrator');
        $this->health($compliance)->assertSee('Run Again');
        $this->actingAs($compliance)->post(route('admin.service-health.retry', $uuid))->assertRedirect();
    }
}
