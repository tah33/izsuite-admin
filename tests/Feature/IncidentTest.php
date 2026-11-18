<?php

namespace Tests\Feature;

use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Incident;
use App\Models\Admin\Role;
use App\Models\User\User;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Doc 15 Phase D3b (section 15.23): incidents - opened with a severity, the
 * service and the admin responsible; moved on by notes (status, who is
 * responsible) until resolved, or back; listed unresolved first, and shown
 * on Service Health and the Overview.
 */
class IncidentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $rafi;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed([RoleSeeder::class, PaymentMethodSeeder::class]);
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));
        Http::fake(['*/health' => Http::response(['status' => 'up'])]);
        Storage::fake((string) config('files.storage'));
        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Nadia', 'last_name' => 'Karim']);
        $this->rafi  = User::factory()->create(['role_id' => Role::where('slug', 'compliance-and-security-administrator')->value('id'), 'first_name' => 'Rafi', 'last_name' => 'Uddin']);
    }

    private function open(array $data = []): Incident
    {
        $this->actingAs($this->admin)->post(route('admin.incidents.store'), [
            'title' => 'Invoice e-mails are not going out', 'service' => 'mail', 'severity' => 'major', 'started_at' => '2026-11-14T08:30',
            'responsible_id' => $this->rafi->id, 'summary' => 'The mail server refuses every message since 08:30.', ...$data,
        ])->assertRedirect();

        return Incident::latest('id')->firstOrFail();
    }

    private function note(Incident $incident, array $data, ?User $as = null)
    {
        return $this->actingAs($as ?? $this->admin)->post(route('admin.incidents.note', $incident->id), [
            'body' => 'Checked the mail server.', 'status' => $incident->fresh()->status, 'responsible_id' => (string) $incident->fresh()->responsible_id, ...$data,
        ]);
    }

    public function test_an_incident_opens_with_its_summary_as_the_first_note_and_moves_on_by_notes_until_resolved(): void
    {
        $incident = $this->open();
        $this->assertSame([Incident::OPEN, 'mail', 'major', $this->rafi->id, '2026-11-14 08:30:00', null], [$incident->status, $incident->service, $incident->severity, $incident->responsible_id, $incident->started_at->toDateTimeString(), $incident->resolved_at]);
        $this->assertSame([['The mail server refuses every message since 08:30.', null, Incident::OPEN, 'Rafi Uddin']], $incident->notes->map(fn ($note) => [$note->body, $note->status_from, $note->status_to, $note->responsible_to])->all());

        // Investigating, and Nadia takes it on.
        $this->note($incident, ['body' => 'The provider has an outage.', 'status' => Incident::INVESTIGATING, 'responsible_id' => (string) $this->admin->id])->assertRedirect(route('admin.incidents.show', $incident->id));
        $this->assertSame([Incident::INVESTIGATING, $this->admin->id], [$incident->fresh()->status, $incident->fresh()->responsible_id]);

        // Resolved, stamped; back to monitoring, the stamp goes; nobody responsible.
        $this->travel(2)->hours();
        $this->note($incident, ['body' => 'Mail goes out again.', 'status' => Incident::RESOLVED]);
        $this->assertSame('2026-11-14 12:00:00', $incident->fresh()->resolved_at->toDateTimeString());
        $this->note($incident, ['body' => 'Some still bounce.', 'status' => Incident::MONITORING, 'responsible_id' => '']);
        $this->assertSame([Incident::MONITORING, null, null], [$incident->fresh()->status, $incident->fresh()->resolved_at, $incident->fresh()->responsible_id]);

        $this->assertSame([
            ['Some still bounce.', Incident::RESOLVED, Incident::MONITORING, 'Nobody'],
            ['Mail goes out again.', Incident::INVESTIGATING, Incident::RESOLVED, null],
            ['The provider has an outage.', Incident::OPEN, Incident::INVESTIGATING, 'Nadia Karim'],
        ], $incident->notes()->latest('id')->limit(3)->get()->map(fn ($note) => [$note->body, $note->status_from, $note->status_to, $note->responsible_to])->all());

        $this->get(route('admin.incidents.show', $incident->id))->assertOk()
            ->assertSeeInOrder(['Invoice e-mails are not going out', 'Major', 'Monitoring', 'E-mail', 'Nobody yet', 'Nov 14, 2026 08:30 UTC', 'Timeline', 'Resolved → Monitoring', 'Responsible: Nobody', 'Some still bounce.']);
        $this->assertSame([
            'Added a note to incident "Invoice e-mails are not going out": Resolved to Monitoring',
            'Added a note to incident "Invoice e-mails are not going out": Investigating to Resolved',
            'Added a note to incident "Invoice e-mails are not going out": Open to Investigating',
            'Opened incident "Invoice e-mails are not going out" (major)',
        ], AdminAuditLog::where('description', 'like', '%incident%')->latest('id')->pluck('description')->all());
    }

    public function test_the_form_and_notes_are_checked(): void
    {
        $this->actingAs($this->admin)->post(route('admin.incidents.store'), ['title' => '', 'severity' => 'major', 'started_at' => '2026-11-14T08:00'])->assertSessionHasErrors('title');
        $this->post(route('admin.incidents.store'), ['title' => 'Later', 'severity' => 'major', 'started_at' => '2026-11-15T08:00'])->assertSessionHasErrors(['started_at' => 'An incident cannot start in the future.']);
        $this->post(route('admin.incidents.store'), ['title' => 'Odd', 'service' => 'coffee', 'severity' => 'major', 'started_at' => '2026-11-14T08:00'])->assertSessionHasErrors('service');
        // Only staff who may sign in are made responsible.
        $client = User::factory()->create();
        $this->post(route('admin.incidents.store'), ['title' => 'Odd', 'severity' => 'minor', 'started_at' => '2026-11-14T08:00', 'responsible_id' => $client->id])->assertSessionHasErrors(['responsible_id' => 'Choose an admin who may sign in.']);
        $this->assertSame(0, Incident::count());

        $incident = $this->open();
        $this->note($incident, ['body' => ''])->assertSessionHasErrors(['body' => 'Write what happened or what was done.']);
        $this->note($incident, ['status' => 'gone'])->assertSessionHasErrors('status');

        // Edited, but not who is responsible: a note changes that.
        $this->put(route('admin.incidents.update', $incident->id), ['title' => 'All e-mails are failing', 'service' => 'mail', 'severity' => 'critical', 'started_at' => '2026-11-14T08:15'])->assertRedirect(route('admin.incidents.show', $incident->id));
        $this->assertSame(['All e-mails are failing', 'critical', '2026-11-14 08:15:00'], [$incident->fresh()->title, $incident->fresh()->severity, $incident->fresh()->started_at->toDateTimeString()]);
        $this->put(route('admin.incidents.update', $incident->id), ['title' => 'X', 'severity' => 'minor', 'started_at' => '2026-11-14T08:15', 'responsible_id' => $this->admin->id])->assertSessionHasErrors('responsible_id');
    }

    public function test_they_list_unresolved_first_and_show_on_service_health_and_the_overview(): void
    {
        $resolved = $this->open(['title' => 'Slow PDFs', 'service' => 'pdf', 'severity' => 'minor', 'started_at' => '2026-11-14T09:00']);
        $this->note($resolved, ['status' => Incident::RESOLVED]);
        $this->open(['title' => 'Card payments refused', 'service' => 'payments', 'severity' => 'critical', 'started_at' => '2026-11-13T07:00']);

        $names = fn (array $query = []) => collect($this->get(route('admin.incidents.index', $query))->assertOk()->viewData('incidents')->items())->pluck('title')->all();
        $this->assertSame(['Card payments refused', 'Slow PDFs'], $names());
        $this->assertSame(['Card payments refused'], $names(['status' => 'unresolved']));
        $this->assertSame(['Slow PDFs'], $names(['service' => 'pdf']));
        $this->assertSame(['Card payments refused'], $names(['severity' => 'critical']));
        $this->assertSame(['Slow PDFs'], $names(['search' => 'pdf']));

        // Service Health: the one not resolved, and Open an incident beside a service that warns (e-mail only goes to the log).
        config(['mail.default' => 'log']);
        $this->get(route('admin.service-health.index'))->assertOk()
            ->assertSeeInOrder(['Incidents Not Resolved', 'Card payments refused', 'Critical', 'Open', 'Card Payments', 'Rafi Uddin'])
            ->assertDontSee('Slow PDFs')
            ->assertSee(route('admin.incidents.create', ['service' => 'mail']), false);
        $this->get(route('admin.incidents.create', ['service' => 'mail']))->assertOk()->assertSee('<option value="mail" selected>', false);

        $this->get(route('admin.overview'))->assertOk()->assertSeeInOrder(['Platform Alerts', '1 incident not resolved', 'Card payments refused', 'Critical']);
    }

    public function test_compliance_runs_incidents_support_adds_notes_others_look(): void
    {
        $incident = $this->open();
        $role     = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);

        $support = $role('support-administrator');
        $this->actingAs($support)->get(route('admin.incidents.show', $incident->id))->assertOk()->assertSee('Add a Note')->assertDontSee(route('admin.incidents.edit', $incident->id));
        $this->note($incident, ['body' => 'Three businesses asked about their invoices.'], $support)->assertRedirect();
        $this->actingAs($support)->get(route('admin.incidents.create'))->assertForbidden();

        foreach (['billing-administrator', 'read-only-administrator'] as $slug) {
            $as = $role($slug);
            $this->actingAs($as)->get(route('admin.incidents.index'))->assertOk()->assertDontSee('Open Incident');
            $this->actingAs($as)->get(route('admin.incidents.show', $incident->id))->assertOk()->assertDontSee('Add a Note');
            $this->note($incident, [], $as)->assertForbidden();
        }

        $this->actingAs($role('product-administrator'))->get(route('admin.incidents.index'))->assertForbidden();
        $this->actingAs($this->rafi)->get(route('admin.incidents.edit', $incident->id))->assertOk();
    }
}
