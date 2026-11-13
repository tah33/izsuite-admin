<?php

namespace Tests\Feature;

use App\Models\Admin\AdminAuditLog;
use App\Models\Admin\Faq;
use App\Models\Admin\Role;
use App\Models\Billing\PaymentMethod;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Support\AdminRoles;
use App\Support\RecoveryCodes;
use App\Support\Totp;
use Database\Seeders\PaymentMethodSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use LogicException;
use Tests\TestCase;

/**
 * Doc 15 Phase A2: the admin panel's audit log (section 15.26) - every admin
 * action and sign-in, apart from the businesses' Activity Logs, searched,
 * read and exported, never changed - the starting admin roles (section
 * 15.3), and critical actions that ask for the password again and a reason
 * (section 15.25).
 */
class AdminAuditTest extends TestCase
{
    use RefreshDatabase;

    private const PASSWORD = 'Secret#Pass2026';

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1.
        $this->seed(RoleSeeder::class);

        Mail::fake();
    }

    private function admin(string $role = 'staff'): User
    {
        return User::create([
            'role_id'    => Role::where('slug', $role)->value('id'),
            'first_name' => 'Nadia', 'last_name' => ucfirst(str_replace('-', ' ', $role)), 'email' => $role.'.'.uniqid().'@izsuite.test',
            'password'   => self::PASSWORD, 'email_verified_at' => now(), 'status' => 'active',
        ]);
    }

    private function superAdmin(): User
    {
        $user = $this->admin();
        $user->forceFill(['role_id' => Role::SUPER_ADMIN_ID])->save();

        return $user->fresh();
    }

    private function confirmation(array $fields = []): array
    {
        return ['confirm_password' => self::PASSWORD, 'reason' => 'Agreed with the finance team', ...$fields];
    }

    /* ---------------------------------------------------------- recording */

    public function test_admin_actions_go_to_the_audit_log_and_never_a_business_log(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);

        $this->postJson(route('admin.faqs.store'), ['question' => 'How do I pay?', 'status' => 'published'])->assertOk();
        $faq = Faq::firstOrFail();
        $this->putJson(route('admin.faqs.update', $faq->id), ['question' => 'How do I pay my invoice?', 'status' => 'draft']);

        $entries = AdminAuditLog::orderBy('id')->get();
        $this->assertSame(['created', 'updated'], $entries->pluck('action')->all());
        $this->assertSame([$super->id, $super->name, 'Super Admin', '127.0.0.1'], [$entries[0]->user_id, $entries[0]->admin_name, $entries[0]->role_name, $entries[0]->ip_address]);
        $this->assertSame(['Faq', $faq->id], [class_basename($entries[1]->model_type), $entries[1]->model_id]);
        $this->assertContains(['setting' => 'question', 'from' => 'How do I pay?', 'to' => 'How do I pay my invoice?'], $entries[1]->changes);
        $this->assertSame('succeeded', $entries[1]->result);
        $this->assertSame(0, ActivityLog::count());
    }

    public function test_sign_ins_sign_outs_and_failed_attempts_are_audited(): void
    {
        $member = $this->admin();
        $secret = Totp::secret();
        $member->forceFill(['two_factor_secret' => $secret, 'two_factor_confirmed_at' => now(), 'two_factor_recovery_codes' => [RecoveryCodes::hash('AAAAA-BBBBB')]])->save();

        $this->post('/login', ['email' => $member->email, 'password' => 'Wrong#Pass2026']);
        $this->post('/login', ['email' => 'nobody@izsuite.test', 'password' => 'Wrong#Pass2026']);
        $this->post('/login', ['email' => $member->email, 'password' => self::PASSWORD]);
        $this->post(route('login.two-factor'), ['code' => Totp::code($secret, Totp::step())])->assertRedirect(route('admin.overview'));
        $this->post('/logout');

        $this->assertSame([
            ['login', 'Failed sign-in: wrong e-mail or password', 'failed', $member->id],
            ['login', 'Failed sign-in: wrong e-mail or password', 'failed', null],
            ['login', 'Signed in to the admin panel', 'succeeded', $member->id],
            ['logout', 'Signed out of the admin panel', 'succeeded', $member->id],
        ], AdminAuditLog::orderBy('id')->get()->map(fn (AdminAuditLog $entry) => [$entry->action, $entry->description, $entry->result, $entry->user_id])->all());
        $this->assertSame('nobody@izsuite.test', AdminAuditLog::where('result', 'failed')->latest('id')->first()->properties['email']);
    }

    public function test_secrets_are_named_but_never_kept(): void
    {
        $this->actingAs($this->superAdmin());

        $this->put(route('admin.settings.update'), ['_group' => 'mail', 'settings' => [
            'smtp_enabled' => '1', 'smtp_host' => 'smtp.example.com', 'smtp_port' => '587', 'smtp_encryption' => 'tls',
            'smtp_username' => 'mailer@example.com', 'smtp_password' => 'Pa55word-Kept-Secret', 'smtp_from_address' => 'no-reply@example.com', 'smtp_from_name' => 'izSuite',
        ]])->assertSessionHasNoErrors();

        $entry = AdminAuditLog::where('description', 'like', 'Updated site settings%')->sole();
        $this->assertContains(['setting' => 'smtp_host', 'from' => null, 'to' => 'smtp.example.com'], $entry->changes);
        $this->assertSame(['smtp_password'], $entry->properties['secrets_changed']);

        // A gateway's keys, in test mode: no confirmation needed, and no key kept.
        $this->seed(PaymentMethodSeeder::class);
        $stripe = PaymentMethod::where('slug', 'stripe')->firstOrFail();
        $this->put(route('admin.payment-methods.update', $stripe->id), ['is_active' => '1', 'is_sandbox' => '1', 'credentials' => ['test' => ['secret_key' => 'sk_test_never9876', 'webhook_secret' => 'whsec_never5555']]])->assertRedirect();

        $everything = json_encode(AdminAuditLog::all()->toArray());
        $this->assertStringNotContainsString('Pa55word-Kept-Secret', $everything);
        $this->assertStringNotContainsString('sk_test_never9876', $everything);
        $this->assertStringNotContainsString('whsec_never5555', $everything);
    }

    public function test_an_entry_is_never_changed_or_deleted(): void
    {
        $this->actingAs($this->superAdmin())->postJson(route('admin.faqs.store'), ['question' => 'How do I pay?', 'status' => 'published']);
        $entry = AdminAuditLog::firstOrFail();

        $this->assertThrows(fn () => $entry->update(['description' => 'Nothing happened']), LogicException::class);
        $this->assertThrows(fn () => $entry->delete(), LogicException::class);
        $this->assertSame('Created FAQ "How do I pay?"', $entry->fresh()->description);
    }

    /* ---------------------------------------------------------- the page */

    public function test_the_audit_log_is_searched_read_and_exported_by_roles_allowed_to(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super)->postJson(route('admin.faqs.store'), ['question' => 'How do I pay?', 'status' => 'published']);
        $this->postJson(route('admin.faqs.store'), ['question' => 'Where is my receipt?', 'status' => 'draft']);
        $first = AdminAuditLog::orderBy('id')->firstOrFail();

        $this->get(route('admin.audit-log.index', ['search' => 'receipt']))->assertOk()
            ->assertSee('Created FAQ &quot;Where is my receipt?&quot;', false)->assertDontSee('How do I pay?');
        $this->get(route('admin.audit-log.index', ['result' => 'refused']))->assertOk()->assertSee('No entries match.');
        $this->get(route('admin.audit-log.show', $first->id))->assertOk()->assertSee('How do I pay?')->assertSee('Super Admin');

        // Compliance and Security exports; the export is itself logged.
        $compliance = $this->admin('compliance-and-security-administrator');
        $csv        = $this->actingAs($compliance)->get(route('admin.audit-log.export', ['action' => 'created']))->assertOk()->streamedContent();
        $this->assertStringContainsString('Time,Admin,Role,Action,Description', $csv);
        $this->assertStringContainsString('Where is my receipt?', $csv);
        $this->assertSame('Exported 2 audit entries', AdminAuditLog::where('action', 'exported')->sole()->description);

        // Read-Only reads, but neither exports nor opens a form: each refusal is logged.
        $readOnly = $this->admin('read-only-administrator');
        $this->actingAs($readOnly)->get(route('admin.audit-log.index'))->assertOk()->assertDontSee('Export CSV');
        $this->get(route('admin.audit-log.show', $first->id))->assertOk();
        $this->get(route('admin.audit-log.export'))->assertForbidden();
        $this->get(route('admin.faqs.create'))->assertForbidden();
        $this->assertSame(2, AdminAuditLog::where(['user_id' => $readOnly->id, 'action' => 'denied', 'result' => 'refused'])->count());
    }

    /* ---------------------------------------------------------- the starting roles */

    public function test_the_starting_roles_open_their_own_areas(): void
    {
        $this->assertSame(
            ['Product Administrator', 'Billing Administrator', 'Support Administrator', 'Compliance and Security Administrator', 'Read-Only Administrator'],
            Role::whereIn('slug', array_keys(AdminRoles::templates()))->orderBy('id')->pluck('name')->all(),
        );

        $opens = fn (string $role, string $route) => $this->actingAs($this->admin($role))->get(route($route))->status();

        $this->assertSame([200, 403], [$opens('product-administrator', 'admin.apps.index'), $opens('product-administrator', 'admin.payment-methods.index')]);
        $this->assertSame([200, 403], [$opens('billing-administrator', 'admin.payment-methods.index'), $opens('billing-administrator', 'admin.apps.index')]);
        $this->assertSame([200, 403], [$opens('support-administrator', 'admin.clients.index'), $opens('support-administrator', 'admin.packages.index')]);
        $this->assertSame([200, 403], [$opens('compliance-and-security-administrator', 'admin.audit-log.index'), $opens('compliance-and-security-administrator', 'admin.faqs.index')]);
        $this->assertSame([200, 403], [$opens('read-only-administrator', 'admin.faqs.index'), $opens('read-only-administrator', 'admin.settings.index')]);

        // Made once: a role changed since keeps its changes.
        Role::where('slug', 'product-administrator')->update(['permissions' => json_encode(['admin.faqs.index'])]);
        AdminRoles::createMissing();
        $this->assertSame(['admin.faqs.index'], Role::where('slug', 'product-administrator')->first()->permissions);
    }

    /* ---------------------------------------------------------- critical actions */

    public function test_changing_every_plans_apps_needs_the_password_and_a_reason(): void
    {
        $super = $this->superAdmin();
        $this->actingAs($super);

        $this->putJson(route('admin.package-access.update'), ['access' => []])
            ->assertUnprocessable()->assertJsonValidationErrors(['confirm_password', 'reason']);

        $this->putJson(route('admin.package-access.update'), ['access' => [], ...$this->confirmation(['confirm_password' => 'Wrong#Pass2026'])])
            ->assertUnprocessable()->assertJsonValidationErrors(['confirm_password' => 'That password is not right.']);
        $this->assertSame('refused', AdminAuditLog::where('action', 'refused')->sole()->result);

        $this->putJson(route('admin.package-access.update'), ['access' => [], ...$this->confirmation()])->assertOk();

        $entry = AdminAuditLog::where('description', 'Updated package access')->sole();
        $this->assertSame(['Agreed with the finance team', 'password'], [$entry->reason, $entry->approval]);
    }

    public function test_a_payment_method_taking_real_payments_needs_the_password_and_a_reason(): void
    {
        $this->actingAs($this->superAdmin());
        $bank = PaymentMethod::create(['type' => 'offline', 'name' => 'Bank Transfer 2', 'slug' => 'bank-transfer-2', 'instructions' => 'Old account', 'is_active' => true, 'sort_order' => 9]);
        $form = ['name' => 'Bank Transfer 2', 'instructions' => 'New account', 'is_active' => '1'];

        $this->putJson(route('admin.payment-methods.update', $bank->id), $form)->assertUnprocessable()->assertJsonValidationErrors(['confirm_password', 'reason']);
        $this->assertSame('Old account', $bank->fresh()->instructions);

        $this->put(route('admin.payment-methods.update', $bank->id), [...$form, ...$this->confirmation()])->assertRedirect();
        $this->assertSame('New account', $bank->fresh()->instructions);
        $this->assertSame('Agreed with the finance team', AdminAuditLog::where('model_type', PaymentMethod::class)->latest('id')->first()->reason);

        // Switched off, it takes nothing: no confirmation to change or delete it.
        $bank->forceFill(['is_active' => false])->save();
        $this->put(route('admin.payment-methods.update', $bank->id), ['name' => 'Bank Transfer 2', 'instructions' => 'Older account'])->assertRedirect();
        $this->delete(route('admin.payment-methods.destroy', $bank->id))->assertRedirect();
        $this->assertNull($bank->fresh());
    }

    public function test_too_many_wrong_passwords_lock_critical_actions_for_ten_minutes(): void
    {
        $this->actingAs($this->superAdmin());

        foreach (range(1, 5) as $try) {
            $this->putJson(route('admin.package-access.update'), ['access' => [], ...$this->confirmation(['confirm_password' => 'Wrong#Pass2026'])])->assertUnprocessable();
        }

        $this->putJson(route('admin.package-access.update'), ['access' => [], ...$this->confirmation()])
            ->assertUnprocessable()->assertJsonValidationErrors(['confirm_password' => 'Too many wrong passwords. Try again in 10 minutes.']);

        $this->travel(11)->minutes();
        $this->putJson(route('admin.package-access.update'), ['access' => [], ...$this->confirmation()])->assertOk();
    }
}
