<?php

namespace Tests\Feature;

use App\Mail\TestMail;
use App\Models\Admin\Role;
use App\Models\Admin\Setting;
use App\Models\User\User;
use App\Providers\ConfigServiceProvider;
use App\Services\Admin\SettingService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class MailSettingsTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        // id is not fillable on Role, so force it: the middleware keys off id === 1.
        Role::query()->firstOrNew(['id' => Role::SUPER_ADMIN_ID])->forceFill([
            'id'          => Role::SUPER_ADMIN_ID,
            'name'        => 'Super Admin',
            'slug'        => 'super-admin',
            'permissions' => [],
        ])->save();

        return User::create([
            'role_id'           => Role::SUPER_ADMIN_ID,
            'name'              => 'Admin',
            'email'             => 'admin@example.test',
            'password'          => Hash::make('password'),
            'email_verified_at' => now(),
        ]);
    }

    /**
     * A valid mail-group payload. The form always posts every field.
     */
    private function payload(array $overrides = []): array
    {
        return ['_group' => 'mail', 'settings' => array_merge([
            'smtp_enabled'      => '1',
            'smtp_host'         => 'smtp.example.com',
            'smtp_port'         => '587',
            'smtp_encryption'   => 'tls',
            'smtp_username'     => 'mailer@example.com',
            'smtp_password'     => 's3cret',
            'smtp_from_address' => 'no-reply@example.com',
            'smtp_from_name'    => 'Example',
        ], $overrides)];
    }

    public function test_settings_page_shows_the_mail_tab_with_smtp_fields(): void
    {
        $response = $this->actingAs($this->admin())->get(route('admin.settings.index'));

        $response->assertOk();
        $response->assertSee('data-tab="mail"', false);
        $response->assertSee('settings[smtp_host]', false);
        $response->assertSee('settings[smtp_port]', false);
        $response->assertSee('settings[smtp_encryption]', false);
        $response->assertSee('settings[smtp_from_address]', false);
        $response->assertSee(route('admin.settings.test-mail'), false);
    }

    public function test_admin_can_save_mail_settings(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success');

        $this->assertSame('smtp.example.com', setting('smtp_host'));
        $this->assertSame('587', setting('smtp_port'));
        $this->assertSame('s3cret', setting('smtp_password'));
        $this->assertSame('mail', Setting::where('key', 'smtp_host')->value('group'));
    }

    public function test_blank_password_keeps_the_stored_one(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload());
        $this->assertSame('s3cret', setting('smtp_password'));

        $this->actingAs($admin)
            ->put(route('admin.settings.update'), $this->payload(['smtp_password' => '']))
            ->assertSessionHasNoErrors();

        $this->assertSame('s3cret', setting('smtp_password'));
    }

    public function test_stored_password_is_never_rendered_in_the_form(): void
    {
        $admin = $this->admin();

        $this->actingAs($admin)->put(route('admin.settings.update'), $this->payload());

        $this->actingAs($admin)
            ->get(route('admin.settings.index'))
            ->assertOk()
            ->assertDontSee('s3cret');
    }

    public function test_enabling_smtp_without_a_host_fails_validation(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), $this->payload(['smtp_host' => '']))
            ->assertSessionHasErrors('settings.smtp_host');

        $this->assertSame('', setting('smtp_host', ''));
    }

    public function test_disabled_smtp_can_be_saved_with_empty_credentials(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), $this->payload([
                'smtp_enabled'      => '0',
                'smtp_host'         => '',
                'smtp_from_address' => '',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('0', setting('smtp_enabled'));
    }

    public function test_invalid_port_and_from_address_are_rejected(): void
    {
        $this->actingAs($this->admin())
            ->put(route('admin.settings.update'), $this->payload([
                'smtp_port'         => '70000',
                'smtp_from_address' => 'not-an-email',
            ]))
            ->assertSessionHasErrors(['settings.smtp_port', 'settings.smtp_from_address']);
    }

    public function test_admin_can_send_a_test_email(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('admin.settings.test-mail'), ['test_email' => 'qa@example.com'])
            ->assertSessionHas('success');

        Mail::assertSent(TestMail::class, fn (TestMail $mail) => $mail->hasTo('qa@example.com'));
    }

    public function test_test_email_requires_a_valid_address(): void
    {
        Mail::fake();

        $this->actingAs($this->admin())
            ->post(route('admin.settings.test-mail'), ['test_email' => 'nope'])
            ->assertSessionHasErrors('test_email');

        Mail::assertNothingSent();
    }

    public function test_saved_settings_drive_the_mailer_config(): void
    {
        app(SettingService::class)->bulkUpdate([
            'smtp_enabled'      => '1',
            'smtp_host'         => 'smtp.example.com',
            'smtp_port'         => '465',
            'smtp_encryption'   => 'ssl',
            'smtp_username'     => 'mailer@example.com',
            'smtp_password'     => 's3cret',
            'smtp_from_address' => 'no-reply@example.com',
            'smtp_from_name'    => 'Example',
        ]);

        (new ConfigServiceProvider($this->app))->boot();

        $this->assertSame('smtp', config('mail.default'));
        // Laravel 12 builds the transport from the scheme, not from "encryption".
        $this->assertSame('smtps', config('mail.mailers.smtp.scheme'));
        $this->assertSame(465, config('mail.mailers.smtp.port'));
        $this->assertSame('no-reply@example.com', config('mail.from.address'));
    }

    public function test_disabled_smtp_leaves_the_env_mailer_alone(): void
    {
        app(SettingService::class)->bulkUpdate([
            'smtp_enabled' => '0',
            'smtp_host'    => 'smtp.example.com',
        ]);

        (new ConfigServiceProvider($this->app))->boot();

        $this->assertNotSame('smtp.example.com', config('mail.mailers.smtp.host'));
    }
}
