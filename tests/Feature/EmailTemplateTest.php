<?php

namespace Tests\Feature;

use App\Mail\EmailTemplateTest as TestMail;
use App\Mail\SubscriptionNoticeMail;
use App\Mail\VerifyEmailOtp;
use App\Mail\WorkspaceInvitation;
use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\EmailTemplate;
use App\Models\Admin\Language;
use App\Models\Admin\Role;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Doc 15 Phase F2 (section 15.20): the platform's e-mails written in the
 * admin, per language - subject, message, button, variables - each save a
 * version, with preview, test delivery and restore. An e-mail is sent in
 * the active template's words for its language, else English's, else the
 * words built into the code.
 */
class EmailTemplateTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** A trial-ending notice, as the subscription cycle sends it. */
    private const TRIAL = ['plan' => 'Standard', 'business' => 'Karim Stores', 'days_left' => 3, 'trial_ends_on' => '20 Nov 2026', 'invoice' => 'IZS-INV-00007', 'total' => 'USD 19.50'];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        foreach (['en' => 'English', 'fr' => 'French'] as $code => $name) {
            Language::create(['name' => $name, 'code' => $code, 'native_name' => $name, 'is_active' => true, 'is_default' => $code === 'en', 'direction' => 'ltr']);
        }

        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID, 'email' => 'rafi@izsuite.example']);
    }

    private function write(string $key, string $language, array $words)
    {
        return $this->actingAs($this->admin)->putJson(route('admin.email-templates.update', [$key, $language]), $words);
    }

    private function trialNotice(?string $locale = null): SubscriptionNoticeMail
    {
        $mail = new SubscriptionNoticeMail(SubscriptionNoticeMail::TRIAL_ENDING, self::TRIAL);

        return $locale ? $mail->locale($locale) : $mail;
    }

    public function test_an_email_is_sent_in_the_active_templates_words_for_its_language(): void
    {
        $this->actingAs($this->admin)->get(route('admin.email-templates.index'))->assertOk()->assertSee('Trial ending')->assertSee('Built-in')->assertSee('French');
        $this->get(route('admin.email-templates.edit', ['subscription.trial_ending', 'en']))->assertOk()->assertSee('{trial_ends_on}')->assertSee('See your subscription');

        $words = ['subject' => 'Only {days_left} days of {plan} left', 'body' => "Hi {business},\n\nYour trial ends on {trial_ends_on}.\n\nPay {invoice} ({total}) to keep it.", 'status' => 'draft'];

        // A draft is kept, not sent.
        $this->write('subscription.trial_ending', 'en', $words)->assertOk()->assertJsonPath('message', 'Saved as version 1, a draft: the built-in words are still sent.');
        $this->assertSame('Your Standard trial ends in 3 days', $this->trialNotice()->envelope()->subject);

        // Active: these words, the variables filled in, with the built-in button.
        $this->write('subscription.trial_ending', 'en', [...$words, 'status' => 'active'])->assertOk()->assertJsonPath('message', 'Saved as version 2, active: it is sent from now.');
        $this->assertSame('Only 3 days of Standard left', $this->trialNotice()->envelope()->subject);
        $html = $this->trialNotice()->render();
        $this->assertStringContainsString('Your trial ends on 20 Nov 2026.', $html);
        $this->assertStringContainsString('Pay IZS-INV-00007 (USD 19.50) to keep it.', $html);
        $this->assertStringContainsString('See your subscription', $html);

        // In French: English's, until French has its own.
        $this->assertSame('Only 3 days of Standard left', $this->trialNotice('fr')->envelope()->subject);
        $this->write('subscription.trial_ending', 'fr', ['subject' => 'Plus que {days_left} jours', 'body' => 'Bonjour {business}.', 'status' => 'active'])->assertOk();
        $this->assertSame('Plus que 3 jours', $this->trialNotice('fr')->envelope()->subject);
        $this->assertSame('Only 3 days of Standard left', $this->trialNotice()->envelope()->subject);

        // Restoring version 1 makes it the newest, a draft again: the built-in words.
        $this->actingAs($this->admin)->postJson(route('admin.email-templates.restore', ['subscription.trial_ending', 'en', 1]))->assertOk()->assertJsonPath('message', 'Version 1 is restored as version 3.');
        $this->assertSame([3, 'draft'], [EmailTemplate::where('language', 'en')->sole()->version, EmailTemplate::where('language', 'en')->sole()->status]);
        $this->assertSame('Your Standard trial ends in 3 days', $this->trialNotice()->envelope()->subject);
        $this->get(route('admin.email-templates.edit', ['subscription.trial_ending', 'en']))->assertSeeInOrder(['Version History', 'v3', 'v2', 'v1']);

        $this->assertSame(4, AdminAuditLog::where('description', 'like', 'Saved e-mail "Trial ending"%')->count());
    }

    public function test_the_account_emails_use_theirs_too(): void
    {
        $this->write('account.verify_email', 'en', ['subject' => 'Your izSuite code: {code}', 'body' => 'Enter {code} within {minutes} minutes.', 'status' => 'active'])->assertOk();
        $this->write('account.invitation', 'en', ['subject' => '{inviter} asks you to join {business}', 'body' => 'Hi {invitee}, you would be {role}. The invitation lasts {days} days.', 'status' => 'active'])->assertOk();

        $code = new VerifyEmailOtp('482913', 10);
        $this->assertSame('Your izSuite code: 482913', $code->envelope()->subject);
        $this->assertStringContainsString('Enter 482913 within 10 minutes.', $code->render());
        $this->assertStringNotContainsString('Accept', $code->render());

        $invitation = new WorkspaceInvitation('Nadia', 'Karim Stores', 'Karim Uddin', 'Accountant', 'https://app.example/invitations/abc', 7);
        $this->assertSame('Karim Uddin asks you to join Karim Stores', $invitation->envelope()->subject);
        $this->assertStringContainsString('https://app.example/invitations/abc', $invitation->render());
        $this->assertStringContainsString('Accept the invitation', $invitation->render());
    }

    public function test_preview_and_test_delivery_show_the_saved_words_with_samples(): void
    {
        Mail::fake();

        // Nothing saved yet: nothing to preview or send.
        $this->actingAs($this->admin)->postJson(route('admin.email-templates.test', ['subscription.grace', 'en']))->assertJsonValidationErrors(['template']);

        $this->write('subscription.grace', 'en', ['subject' => '{days_left} days left to renew {plan}', 'body' => 'Pay {invoice} by {last_day}.', 'button' => 'Renew now', 'status' => 'draft'])->assertOk();

        $this->get(route('admin.email-templates.preview', ['subscription.grace', 'en']))->assertOk()->assertSee('4 days left to renew Standard')->assertSee('Pay IZS-INV-00012 by 13 Dec 2026.')->assertSee('Renew now');

        $this->postJson(route('admin.email-templates.test', ['subscription.grace', 'en']))->assertOk()->assertJsonPath('message', 'A test is on its way to rafi@izsuite.example.');
        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('rafi@izsuite.example') && $mail->envelope()->subject === '[Test] 4 days left to renew Standard');
    }

    public function test_who_may_write_them_and_what_they_may_write(): void
    {
        $support = User::factory()->create(['role_id' => Role::where('slug', 'support-administrator')->value('id')]);

        $this->write('subscription.ended', 'en', ['subject' => 'Your {plan} plan has ended', 'body' => 'Back on Billing Starter.', 'status' => 'active'])->assertOk();

        $this->actingAs($support)->get(route('admin.email-templates.index'))->assertOk();
        $this->actingAs($support)->get(route('admin.email-templates.preview', ['subscription.ended', 'en']))->assertOk()->assertSee('Your Standard plan has ended');
        $this->actingAs($support)->get(route('admin.email-templates.edit', ['subscription.ended', 'en']))->assertForbidden();

        // Only the e-mails izSuite sends, in a language it has on.
        $this->actingAs($this->admin)->get(route('admin.email-templates.edit', ['subscription.unknown', 'en']))->assertNotFound();
        $this->get(route('admin.email-templates.edit', ['subscription.ended', 'de']))->assertNotFound();
        $this->write('subscription.ended', 'en', ['subject' => '', 'body' => '', 'status' => 'live'])->assertJsonValidationErrors(['subject', 'body', 'status']);
    }
}
