<?php

namespace Tests\Feature;

use App\Mail\AccountNoticeMail;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\PrivacyRequest;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Support\BusinessStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use ZipArchive;

/**
 * Doc 15 Phase G1 (section 15.24): privacy requests - a business's data
 * exported, an account closed, data corrected - each worked only once the
 * person asking is checked, every step audited. An export is a ZIP of CSV
 * files whose link lasts the configured days. Saturday 14 November 2026.
 */
class PrivacyRequestTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private User $owner;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Mail::fake();
        Storage::fake('local');
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));

        $this->admin    = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'first_name' => 'Rafi']);
        $this->owner    = User::factory()->create(['email' => 'karim@stores.example', 'first_name' => 'Karim']);
        $this->business = $this->owner->workspaces()->create(['name' => 'Karim Stores']);
        Contact::create(['workspace_id' => $this->business->id, 'contact_type' => 'individual', 'role' => 'customer', 'name' => 'Nadia Rahman', 'email' => 'nadia@example.com']);
    }

    private function log(array $fields = [])
    {
        return $this->actingAs($this->admin)->postJson(route('admin.privacy-requests.store'), [
            'type' => PrivacyRequest::EXPORT, 'requester_name' => 'Karim Uddin', 'requester_email' => 'karim@stores.example', 'received_via' => 'email',
            'received_on' => '2026-11-13', 'details' => 'Please send me all of my business data.', 'account_email' => 'karim@stores.example',
            'business' => $this->business->businessId(), ...$fields,
        ]);
    }

    private function verified(array $fields = []): PrivacyRequest
    {
        $this->log($fields)->assertOk();
        $request = PrivacyRequest::latest('id')->first();
        $this->postJson(route('admin.privacy-requests.verify', $request->id), ['identity_check' => 'Wrote back from the account\'s own e-mail.'])->assertOk();

        return $request->fresh();
    }

    public function test_a_business_export_is_a_zip_of_csv_files_whose_link_expires(): void
    {
        $this->log()->assertOk()->assertJsonPath('message', 'Privacy request PR-00001 is logged. Check who is asking before working on it.');
        $request = PrivacyRequest::sole();
        $this->assertSame(['received', '2026-12-13'], [$request->status, $request->due_on->toDateString()]);

        // Nothing is done before the person asking is checked.
        $this->postJson(route('admin.privacy-requests.export', $request->id))->assertJsonValidationErrors(['request' => 'Check who is asking']);
        $this->postJson(route('admin.privacy-requests.verify', $request->id), ['identity_check' => 'Wrote back from the account\'s own e-mail.'])->assertOk();

        $this->postJson(route('admin.privacy-requests.export', $request->id))->assertOk();
        $request->refresh();
        $this->assertSame(PrivacyRequest::COMPLETED, $request->status);
        $this->assertSame('2026-11-21 10:00', $request->export_expires_at->format('Y-m-d H:i'));
        Storage::disk('local')->assertExists($request->export_path);

        $zip = new ZipArchive;
        $zip->open(Storage::disk('local')->path($request->export_path));
        $this->assertNotFalse($zip->locateName('contacts.csv'));
        $this->assertNotFalse($zip->locateName('README.txt'));
        $this->assertStringContainsString('Nadia Rahman', $zip->getFromName('contacts.csv'));
        $this->assertStringNotContainsString('password', $zip->getFromName('members.csv'));
        $zip->close();

        // Its link goes to the person who asked, and works until it expires.
        $link = null;
        Mail::assertQueued(AccountNoticeMail::class, function (AccountNoticeMail $mail) use (&$link) {
            $link = $mail->actionUrl;

            return $mail->hasTo('karim@stores.example') && $mail->title === 'Your izSuite data export is ready';
        });

        $this->get($link)->assertOk()->assertDownload('izsuite-'.strtolower($this->business->businessId()).'-export.zip');
        $this->assertSame(1, $request->fresh()->export_downloads);
        $this->get($link.'x')->assertForbidden();

        $this->actingAs($this->admin)->get(route('admin.privacy-requests.download', $request->id))->assertOk();
        $this->assertSame(1, AdminAuditLog::where('description', 'Downloaded the data export of privacy request PR-00001')->count());

        // A week later the link has expired, and the file goes.
        $this->travelTo(Carbon::parse('2026-11-21 11:00:00', 'UTC'));
        $this->get($link)->assertForbidden();
        $this->artisan('privacy:purge-exports')->expectsOutput('Deleted 1 expired export.')->assertSuccessful();
        Storage::disk('local')->assertMissing($request->export_path);
        $this->actingAs($this->admin)->get(route('admin.privacy-requests.download', $request->id))->assertStatus(410);
        $this->get(route('admin.privacy-requests.show', $request->id))->assertOk()->assertSee('expired on Nov 21, 2026');
    }

    public function test_an_export_that_cannot_be_sent_is_not_said_to_be_sent(): void
    {
        $request = $this->verified();

        // The e-mail cannot go: the request goes back to checked, without a file, to be started again.
        Mail::shouldReceive('to')->andThrow(new \RuntimeException('The mail server is down.'));
        $this->postJson(route('admin.privacy-requests.export', $request->id))->assertServerError();

        $request->refresh();
        $this->assertSame([PrivacyRequest::VERIFIED, null, null], [$request->status, $request->export_path, $request->outcome]);
        $this->assertSame([], Storage::disk('local')->allFiles('privacy-exports'));
        $this->assertSame(1, AdminAuditLog::where('description', 'like', 'The data export for privacy request PR-00001 could not be built:%')->count());
    }

    public function test_closing_an_account_closes_the_businesses_it_owns_and_keeps_their_records(): void
    {
        $request = $this->verified(['type' => PrivacyRequest::CLOSURE, 'business' => null, 'details' => 'Close my account.']);
        $this->owner->createToken('izsuite-web');

        // A business still billed has its subscription cancelled first.
        $subscription = WorkspaceSubscription::forceCreate(['workspace_id' => $this->business->id, 'package_id' => Package::where('key', 'standard')->value('id'), 'cycle' => 'monthly', 'status' => WorkspaceSubscription::ACTIVE]);
        $this->postJson(route('admin.privacy-requests.close', $request->id))->assertJsonValidationErrors(['request' => 'Cancel the subscription of Karim Stores first']);

        $subscription->forceFill(['status' => 'cancelled'])->save();
        $this->get(route('admin.privacy-requests.show', $request->id))->assertSee('It owns Karim Stores: they close with it.');
        $this->postJson(route('admin.privacy-requests.close', $request->id))->assertOk();

        $this->assertSame([User::CLOSED, 0], [$this->owner->fresh()->status, $this->owner->tokens()->count()]);
        $this->assertSame(BusinessStatus::CLOSED, $this->business->fresh()->status);
        $this->assertSame(1, Contact::where('workspace_id', $this->business->id)->count());
        $this->assertSame(PrivacyRequest::COMPLETED, $request->fresh()->status);
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->title === 'Your izSuite account is closed');

        // It signs in no more; and there is nothing to lock.
        $this->postJson('/api/v1/auth/login', ['email' => 'karim@stores.example', 'password' => 'password'])->assertForbidden()->assertJsonPath('message', 'This account is closed, as its holder asked. Contact izSuite support if you need it again.');
        $this->postJson(route('admin.privacy-requests.close', $request->id))->assertJsonValidationErrors(['request']);
    }

    public function test_a_correction_is_completed_and_a_request_rejected_each_with_the_asker_told(): void
    {
        $correction = $this->verified(['type' => PrivacyRequest::CORRECTION, 'business' => null, 'details' => 'My surname is misspelt.']);

        $this->postJson(route('admin.privacy-requests.complete', $correction->id), ['outcome' => 'Your surname now reads Uddin.', 'notify' => true])->assertOk();
        $this->assertSame([PrivacyRequest::COMPLETED, 'Your surname now reads Uddin.'], [$correction->fresh()->status, $correction->fresh()->outcome]);
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->title === 'Your data is corrected' && in_array('Your surname now reads Uddin.', $mail->lines, true));

        // A completed request is answered: it is not rejected after.
        $this->postJson(route('admin.privacy-requests.reject', $correction->id), ['outcome' => 'Too late.'])->assertJsonValidationErrors(['request']);

        $this->log(['details' => 'Send my data to my new address.'])->assertOk();
        $other = PrivacyRequest::latest('id')->first();
        $this->postJson(route('admin.privacy-requests.reject', $other->id), ['outcome' => 'We could not check that you own the account.', 'notify' => true])->assertOk();
        $this->assertSame(PrivacyRequest::REJECTED, $other->fresh()->status);
        Mail::assertQueued(AccountNoticeMail::class, fn (AccountNoticeMail $mail) => $mail->title === 'About your privacy request');

        $this->assertSame(1, AdminAuditLog::where('description', 'Rejected privacy request '.$other->number())->count());
    }

    public function test_what_a_request_must_name(): void
    {
        $stranger = User::factory()->create(['email' => 'stranger@example.com']);

        $this->log(['account_email' => 'nobody@example.com'])->assertJsonValidationErrors(['account_email' => 'No customer account has this e-mail.']);
        $this->log(['business' => 'BW-999999'])->assertJsonValidationErrors(['business' => 'No business has this ID.']);
        $this->log(['business' => null])->assertJsonValidationErrors(['business' => 'Name the business']);
        $this->log(['account_email' => $stranger->email])->assertJsonValidationErrors(['account_email' => 'not the owner or a member']);
        $this->log(['type' => PrivacyRequest::CLOSURE, 'account_email' => null, 'business' => null])->assertJsonValidationErrors(['account_email' => 'Name the account to close']);
        $this->log(['account_email' => $this->admin->email])->assertJsonValidationErrors(['account_email' => 'No customer account']);
        $this->log(['received_on' => '2026-11-20', 'identity_check' => 'x'])->assertJsonValidationErrors(['received_on']);

        $this->assertSame(0, PrivacyRequest::count());
    }

    public function test_the_list_and_who_may_do_what(): void
    {
        $this->verified();
        $this->travelTo(Carbon::parse('2026-12-20 10:00:00', 'UTC'));

        $this->get(route('admin.privacy-requests.index', ['status' => 'open']))->assertOk()->assertSee('PR-00001')->assertSee('Overdue');
        $this->get(route('admin.privacy-requests.index', ['search' => 'pr-1']))->assertSee('Karim Uddin');

        $support  = User::factory()->create(['role_id' => Role::where('slug', 'support-administrator')->value('id')]);
        $readOnly = User::factory()->create(['role_id' => Role::where('slug', 'read-only-administrator')->value('id')]);
        $request  = PrivacyRequest::sole();

        $this->actingAs($support)->get(route('admin.privacy-requests.show', $request->id))->assertOk()->assertDontSee('Prepare the Export');
        $this->actingAs($support)->postJson(route('admin.privacy-requests.export', $request->id))->assertForbidden();
        $this->actingAs($readOnly)->get(route('admin.privacy-requests.index'))->assertOk();
        $this->actingAs($readOnly)->get(route('admin.privacy-requests.show', $request->id))->assertForbidden();
    }
}
