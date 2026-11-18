<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\SubscriptionRefund;
use App\Models\Billing\WorkspaceSubscription;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceApp;
use App\Models\User\WorkspaceFile;
use App\Models\User\WorkspaceMember;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\BusinessStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Doc 15 Phase D1 (section 15.4): Admin > Overview - the businesses as they
 * are now, by status, plan and subscription; new businesses and accounts,
 * active users and izSuite's billing between two days; the apps most
 * activated, storage and the background jobs - each narrowed by the plan,
 * country, subscription and app filters, the billing by its currency.
 */
class AdminOverviewTest extends TestCase
{
    use RefreshDatabase;

    private const NOW = '2026-11-14 10:00:00';

    private User $admin;

    /** @var array<string, Workspace> */
    private array $business = [];

    /** @var array<string, User> */
    private array $owner = [];

    private Application $sales;

    private Application $purchases;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));
        // The platform's alerts ask the PDF maker and write a probe file (Phase D3a).
        Http::fake(['*/health' => Http::response(['status' => 'up'])]);
        Storage::fake((string) config('files.storage'));
        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        Package::where('key', 'standard')->update(['storage_limit_mb' => 10]);
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'exchange_rate' => 1, 'is_active' => true]);
        Currency::create(['name' => 'Bangladeshi Taka', 'code' => 'BDT', 'symbol' => '৳', 'exchange_rate' => 110, 'is_active' => true]);

        // Karim (Standard, past due) and Nadia (Standard, in grace) signed up in the last 30 days, Uddin (Billing Starter, suspended) too; Rahim (Premium, trial) in September.
        $this->business('karim', 'standard', 'BD', '2026-11-10 09:00:00');
        $this->business('uddin', 'starter', 'BD', '2026-11-01 09:00:00', BusinessStatus::SUSPENDED);
        $this->business('rahim', 'premium', 'IN', '2026-09-15 09:00:00');
        $this->business('nadia', 'standard', 'IN', '2026-11-12 09:00:00');

        $this->subscription('karim', WorkspaceSubscription::ACTIVE, 'USD');
        $this->subscription('rahim', WorkspaceSubscription::TRIALING, 'USD');
        $this->subscription('nadia', WorkspaceSubscription::GRACE, 'BDT');

        $this->sales     = $this->app('sales_lite', 'Sales Lite', 'sales');
        $this->purchases = $this->app('purchases_lite', 'Purchases Lite', 'purchases');
        foreach (['karim', 'uddin', 'rahim'] as $name) {
            $this->activate($name, $this->sales);
        }
        $this->activate('karim', $this->purchases);

        // 5 MB of Standard's 10, and 9 MB - past the 80% warning.
        $this->file('karim', 5 * 1048576);
        $this->file('nadia', 9 * 1048576);

        $this->billing();
    }

    private function business(string $name, string $plan, string $country, string $at, string $status = BusinessStatus::ACTIVE): void
    {
        $this->travelTo(Carbon::parse($at, 'UTC'));
        $owner    = User::factory()->create(['first_name' => ucfirst($name)]);
        $business = $owner->workspaces()->create(['name' => ucfirst($name).' Stores']);
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        Workspace::whereKey($business->id)->update(['package_id' => Package::where('key', $plan)->value('id'), 'status' => $status]);
        $business->businessSetting()->create([
            'user_id' => $owner->id, 'business_name' => ucfirst($name).' Stores', 'language' => 'en', 'country' => $country, 'state' => 'Dhaka',
            'city'    => 'Dhaka', 'zip_code' => '1207', 'address_line_1' => 'Near City Center', 'timezone' => 'UTC', 'stock_accounting_method' => 'fifo',
        ]);

        [$this->owner[$name], $this->business[$name]] = [$owner, $business->fresh()];
    }

    /** A user Karim invited, as a member of his business. */
    private function karimsUser(array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        app(SetUpNewWorkspace::class)->addRoles($this->business['karim'], $this->owner['karim'], 'api');
        $this->business['karim']->members()->create([
            'user_id' => $user->id, 'role_id' => $this->business['karim']->roles()->where('name', 'Sales User')->value('id'),
            'name'    => $user->name, 'email' => $user->email, 'status' => WorkspaceMember::ACTIVE, 'location_access' => WorkspaceMember::ALL_LOCATIONS,
        ]);

        return $user;
    }

    private function subscription(string $name, string $status, string $currency): void
    {
        $this->business[$name]->subscription()->create(['package_id' => $this->business[$name]->package_id, 'cycle' => 'monthly', 'status' => $status, 'currency' => $currency]);
    }

    private function app(string $key, string $name, string $family): Application
    {
        return Application::create(['app_key' => $key, 'name' => $name, 'family_key' => $family, 'edition' => 'lite', 'is_active' => true, 'status' => 'available']);
    }

    private function activate(string $name, Application $app): void
    {
        WorkspaceApp::create(['workspace_id' => $this->business[$name]->id, 'family_key' => $app->family_key, 'app_id' => $app->id, 'status' => WorkspaceApp::STATUS_ACTIVE, 'activated_at' => now()]);
    }

    private function file(string $name, int $bytes): void
    {
        $business = $this->business[$name];

        WorkspaceFile::create([
            'workspace_id' => $business->id, 'attachable_type' => Workspace::class, 'attachable_id' => $business->id, 'collection' => 'attachments',
            'disk' => 'local', 'path' => 'files/'.Str::random(8), 'visibility' => 'private', 'name' => 'scan.pdf', 'size' => $bytes,
        ]);
    }

    private function invoice(string $name, string $kind, string $status, string $currency, string $total, ?string $paidAt = null): SubscriptionInvoice
    {
        static $number = 0;
        $business = $this->business[$name];

        return SubscriptionInvoice::create([
            'workspace_id' => $business->id, 'number' => 'IZS-INV-'.str_pad((string) ++$number, 5, '0', STR_PAD_LEFT), 'kind' => $kind,
            'package_id'   => $business->package_id, 'package_name' => 'Standard', 'cycle' => 'monthly', 'currency' => $currency, 'amount' => $total,
            'total'        => $total, 'status' => $status, 'issued_on' => '2026-11-01', 'due_on' => '2026-11-20', 'paid_at' => $paidAt,
            'billed_to'    => ['name' => ucfirst($name)], 'seller' => ['name' => 'izSuite'],
        ]);
    }

    private function payment(string $name, SubscriptionInvoice $invoice, string $provider, string $status, string $at): SubscriptionPayment
    {
        $this->travelTo(Carbon::parse($at, 'UTC'));
        $payment = SubscriptionPayment::create([
            'workspace_id' => $this->business[$name]->id, 'subscription_invoice_id' => $invoice->id, 'provider' => $provider, 'reference' => Str::random(10),
            'currency'     => $invoice->currency, 'amount' => $invoice->total, 'status' => $status, 'attempted_on' => substr($at, 0, 10),
        ]);
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        return $payment;
    }

    /** Karim's first invoice paid by transfer and 5.00 of it refunded, its renewal's card refused - past due; Nadia's paid in October, in taka. */
    private function billing(): void
    {
        $first   = $this->invoice('karim', SubscriptionInvoice::NEW, SubscriptionInvoice::PAID, 'USD', '19.50', '2026-11-10 12:00:00');
        $paid    = $this->payment('karim', $first, SubscriptionPayment::TRANSFER, SubscriptionPayment::SUCCEEDED, '2026-11-10 12:00:00');
        SubscriptionRefund::create([
            'workspace_id' => $this->business['karim']->id, 'subscription_payment_id' => $paid->id, 'subscription_invoice_id' => $first->id, 'provider' => 'transfer',
            'currency'     => 'USD', 'amount' => '5.00', 'status' => SubscriptionRefund::SUCCEEDED, 'reason' => 'Two days without access', 'confirmed_at' => '2026-11-11 08:00:00',
        ]);
        $renewal = $this->invoice('karim', SubscriptionInvoice::RENEWAL, SubscriptionInvoice::OPEN, 'USD', '19.50');
        $this->payment('karim', $renewal, 'stripe', SubscriptionPayment::FAILED, '2026-11-13 06:00:00');

        $this->invoice('nadia', SubscriptionInvoice::NEW, SubscriptionInvoice::PAID, 'BDT', '2000.00', '2026-10-20 09:00:00');
        // Paid before the days asked for: left out.
        $this->invoice('rahim', SubscriptionInvoice::NEW, SubscriptionInvoice::PAID, 'USD', '49.00', '2026-09-20 09:00:00');
    }

    private function overview(array $query = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->get(route('admin.overview', $query))->assertOk();
    }

    public function test_it_counts_the_businesses_now_the_days_sign_ups_and_use_apps_storage_and_jobs(): void
    {
        // Active in the days: Karim signed in, his invited user changed a record, Uddin used the app, an older account signed in. Rahim last signed in in August.
        $member = $this->karimsUser();
        ActivityLog::create(['user_id' => $member->id, 'workspace_id' => $this->business['karim']->id, 'action' => 'created', 'description' => 'Added a contact']);
        $this->owner['karim']->update(['last_login_at' => '2026-11-13 08:00:00']);
        $this->owner['rahim']->update(['last_login_at' => '2026-08-01 08:00:00']);
        $this->owner['uddin']->createToken('app')->accessToken->forceFill(['last_used_at' => '2026-11-05 08:00:00'])->save();
        $this->travelTo(Carbon::parse('2026-01-01 09:00:00', 'UTC'));
        User::factory()->create(['last_login_at' => '2026-11-13 09:00:00']);
        $this->travelTo(Carbon::parse(self::NOW, 'UTC'));

        DB::table('failed_jobs')->insert([
            ['uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Timed out', 'failed_at' => '2026-11-12 08:00:00'],
            ['uuid' => (string) Str::uuid(), 'connection' => 'redis', 'queue' => 'default', 'payload' => '{}', 'exception' => 'Timed out', 'failed_at' => '2026-09-01 08:00:00'],
        ]);

        // The last 30 days, today included.
        $page = $this->overview();
        $page->assertViewHas('filters', fn (array $filters) => $filters['from'] === '2026-10-16' && $filters['to'] === '2026-11-14');

        $businesses = $page->viewData('businesses');
        $this->assertSame([4, 3, 1, 1, 1, 1], [$businesses['all'], $businesses['active'], $businesses['suspended'], $businesses['trialing'], $businesses['past_due'] ?? null, $businesses['grace']]);
        $this->assertSame([['name' => 'Billing Starter', 'count' => 1], ['name' => 'Standard', 'count' => 2], ['name' => 'Premium', 'count' => 1]], $businesses['plans']);

        $period = $page->viewData('period');
        $this->assertSame([3, 4, 4], [$period['new_businesses'], $period['new_accounts'], $period['active_users']]);
        $this->assertSame([false, 30, 3], [$period['chart']['by_month'], count($period['chart']['values']), array_sum($period['chart']['values'])]);
        $this->assertSame(1, $period['chart']['values'][array_search('Nov 10', $period['chart']['labels'], true)]);

        $this->assertSame([['Sales Lite', 3], ['Purchases Lite', 1]], $page->viewData('apps')->map(fn ($app) => [$app->name, (int) $app->businesses])->all());
        $this->assertSame(['bytes' => 14 * 1048576, 'near_limit' => 1, 'warn_percent' => 80], $page->viewData('storage'));
        $this->assertSame(['failed' => 1, 'kept' => 2, 'waiting' => 0], $page->viewData('jobs'));

        $page->assertSeeInOrder(['Businesses Now', 'All businesses', '4', 'On each plan', 'Billing Starter', 'Standard', 'Premium'])
            ->assertSeeInOrder(['Most Activated Apps', 'Sales Lite', '3 businesses', 'Purchases Lite', '1 business'])
            ->assertSee('14 MB');

        // A whole year goes by month.
        $year = $this->overview(['from' => '2026-01-01', 'to' => '2026-11-14'])->viewData('period');
        $this->assertSame([true, 11, 4], [$year['chart']['by_month'], count($year['chart']['labels']), array_sum($year['chart']['values'])]);
        $this->assertSame(['Sep 2026', 1], [$year['chart']['labels'][8], $year['chart']['values'][8]]);
        $this->assertSame(3, $year['chart']['values'][10]);
    }

    public function test_the_filters_narrow_every_figure_to_the_businesses_they_pick(): void
    {
        $this->karimsUser(['last_login_at' => '2026-11-13 08:00:00']);
        $this->owner['karim']->update(['last_login_at' => '2026-11-13 08:00:00']);

        // India: Rahim (trial, September) and Nadia (grace).
        $india = $this->overview(['country' => 'IN']);
        $this->assertSame([2, 1, 1, 0], [$india->viewData('businesses')['all'], $india->viewData('businesses')['trialing'], $india->viewData('businesses')['grace'], $india->viewData('businesses')['past_due']]);
        $this->assertSame([1, 1, 0], [$india->viewData('period')['new_businesses'], $india->viewData('period')['new_accounts'], $india->viewData('period')['active_users']]);
        $this->assertSame([['Sales Lite', 1]], $india->viewData('apps')->map(fn ($app) => [$app->name, (int) $app->businesses])->all());
        $this->assertSame(['bytes' => 9 * 1048576, 'near_limit' => 1, 'warn_percent' => 80], $india->viewData('storage'));
        $this->assertSame(['BDT'], array_column($india->viewData('billing'), 'currency'));

        // Purchases Lite: Karim's alone - with his invited user.
        $purchases = $this->overview(['app' => $this->purchases->id]);
        $this->assertSame([1, 1], [$purchases->viewData('businesses')['all'], $purchases->viewData('businesses')['past_due']]);
        $this->assertSame([2, 2], [$purchases->viewData('period')['new_accounts'], $purchases->viewData('period')['active_users']]);

        $this->assertSame(2, $this->overview(['package' => 'standard'])->viewData('businesses')['all']);
        $this->assertSame(['all' => 1, 'trialing' => 1], array_intersect_key($this->overview(['subscription' => 'trialing'])->viewData('businesses'), ['all' => 0, 'trialing' => 0]));

        // Billing by currency, never mixed: USD paid 19.50 less 5.00 refunded, and one refused card try; taka 2,000.00.
        $this->assertSame([
            ['currency' => 'BDT', 'invoices' => 1, 'collected' => '2000.00', 'tax' => '0.00', 'refunded' => '0.00', 'net' => '2000.00', 'failed_tries' => 0, 'failed_businesses' => 0, 'failed_amount' => '0.00'],
            ['currency' => 'USD', 'invoices' => 1, 'collected' => '19.50', 'tax' => '0.00', 'refunded' => '5.00', 'net' => '14.50', 'failed_tries' => 1, 'failed_businesses' => 1, 'failed_amount' => '19.50'],
        ], $this->overview()->viewData('billing'));
        $this->assertSame(['USD'], array_column($this->overview(['currency' => 'usd'])->viewData('billing'), 'currency'));
        $this->overview()->assertSeeInOrder(['Subscription Billing', 'BDT', '2,000.00', 'USD', '19.50', '5.00', '14.50', '1 try, 1 business']);

        $this->actingAs($this->admin)->get(route('admin.overview', ['from' => '2026-11-10', 'to' => '2026-11-01']))
            ->assertSessionHasErrors(['to' => 'The last day cannot come before the first.']);
        $this->actingAs($this->admin)->get(route('admin.overview', ['country' => 'XX']))->assertSessionHasErrors('country');
    }

    public function test_billing_shows_only_to_admins_who_may_open_billing_reports(): void
    {
        $role = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);

        $this->overview(as: $role('support-administrator'))->assertViewHas('billing', null)->assertDontSee('Subscription Billing')->assertSee('Businesses Now');
        $this->assertCount(2, $this->overview(as: $role('read-only-administrator'))->viewData('billing'));
        $this->assertCount(2, $this->overview(as: $role('billing-administrator'))->viewData('billing'));
    }
}
