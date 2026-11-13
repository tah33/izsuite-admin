<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\Billing\SubscriptionCredit;
use App\Models\Billing\SubscriptionInvoice;
use App\Models\Billing\SubscriptionPayment;
use App\Models\Billing\SubscriptionRefund;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 15 Phase C2b (section 14): Admin > Billing Reports - invoices paid
 * and refunds by month, plan and currency, with tax and what was kept;
 * invoices open and overdue; failed card payments; and izSuite's credit -
 * never mixing currencies, for the months, currency and plan chosen.
 */
class BillingReportTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $admin;

    private Workspace $karim;

    private Workspace $uddin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-25 10:00:00', 'UTC'));
        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $this->karim = tap($this->businessOf(User::factory()->create()))->update(['name' => 'Karim Stores']);
        $this->uddin = tap($this->businessOf(User::factory()->create()))->update(['name' => 'Uddin Traders']);

        // Paid: Standard in October and November, Premium in November - in US dollars and in euros.
        $this->invoice($this->karim, 'IZS-INV-00001', ['paid_at' => '2026-10-15 09:00:00', 'tax' => '1.95', 'total' => '21.45']);
        $this->invoice($this->karim, 'IZS-INV-00002', ['paid_at' => '2026-11-05 09:00:00', 'tax' => '1.95', 'total' => '21.45']);
        $premium = $this->invoice($this->uddin, 'IZS-INV-00003', ['package_name' => 'Premium', 'paid_at' => '2026-11-20 09:00:00', 'total' => '49.00']);
        $this->invoice($this->uddin, 'IZS-INV-00004', ['package_name' => 'Premium', 'currency' => 'EUR', 'paid_at' => '2026-11-21 09:00:00', 'total' => '45.00']);
        // Waiting: one past its due day, one not yet due.
        $this->invoice($this->karim, 'IZS-INV-00005', ['status' => 'open', 'due_on' => '2026-11-01', 'total' => '21.45']);
        $this->invoice($this->uddin, 'IZS-INV-00006', ['package_name' => 'Premium', 'status' => 'open', 'due_on' => '2026-12-01', 'total' => '49.00']);

        $paid = $this->payment($premium, 'succeeded', '2026-11-20');
        SubscriptionRefund::create([
            'workspace_id' => $this->uddin->id, 'subscription_payment_id' => $paid->id, 'subscription_invoice_id' => $premium->id, 'provider' => 'stripe', 'reference' => 're_1',
            'currency' => 'USD', 'amount' => '10.00', 'status' => 'succeeded', 'reason' => 'Charged twice', 'confirmed_at' => '2026-11-22 09:00:00',
        ]);

        // Card tries that failed: two in November, one waiting for the cardholder in October.
        $open = SubscriptionInvoice::where('number', 'IZS-INV-00005')->sole();
        $this->payment($open, 'failed', '2026-11-08', 'Your card has insufficient funds.');
        $this->payment($open, 'failed', '2026-11-10', 'Your card has insufficient funds.');
        $this->payment(SubscriptionInvoice::where('number', 'IZS-INV-00006')->sole(), 'action_required', '2026-10-30');

        SubscriptionCredit::create(['workspace_id' => $this->karim->id, 'currency' => 'USD', 'amount' => '15.00', 'kind' => 'granted', 'reason' => 'An outage', 'created_at' => '2026-11-02 09:00:00']);
        SubscriptionCredit::create(['workspace_id' => $this->karim->id, 'currency' => 'USD', 'amount' => '-5.00', 'kind' => 'used', 'subscription_invoice_id' => $open->id, 'created_at' => '2026-11-03 09:00:00']);
    }

    private function invoice(Workspace $business, string $number, array $data): SubscriptionInvoice
    {
        return SubscriptionInvoice::create([
            'workspace_id' => $business->id, 'number' => $number, 'kind' => 'new', 'package_id' => Package::where('key', 'standard')->value('id'), 'package_name' => 'Standard',
            'cycle'        => 'monthly', 'currency' => 'USD', 'amount' => '19.50', 'tax' => '0.00', 'total' => '19.50', 'status' => 'paid',
            'issued_on'    => '2026-10-01', 'due_on' => '2026-10-08', 'billed_to' => ['name' => $business->name], 'seller' => ['name' => 'izSuite'], ...$data,
        ]);
    }

    private function payment(SubscriptionInvoice $invoice, string $status, string $on, ?string $note = null): SubscriptionPayment
    {
        $payment = SubscriptionPayment::create([
            'workspace_id' => $invoice->workspace_id, 'subscription_invoice_id' => $invoice->id, 'provider' => 'stripe', 'reference' => 'pi_'.uniqid(),
            'currency'     => $invoice->currency, 'amount' => $invoice->total, 'status' => $status, 'attempted_on' => $on, 'card' => 'Visa ending 4242', 'note' => $note,
        ]);
        $payment->forceFill(['created_at' => "{$on} 10:00:00"])->save();

        return $payment;
    }

    public function test_it_sums_the_last_twelve_months_by_month_plan_and_currency(): void
    {
        $page = $this->actingAs($this->admin)->get(route('admin.billing-reports.index'))->assertOk()->assertSee('Dec 2025 to Nov 2026');

        $this->assertSame([
            ['month' => '2026-11', 'currency' => 'EUR', 'invoices' => 1, 'collected' => '45.00', 'tax' => '0.00', 'refunded' => '0.00', 'kept' => '45.00'],
            ['month' => '2026-11', 'currency' => 'USD', 'invoices' => 2, 'collected' => '70.45', 'tax' => '1.95', 'refunded' => '10.00', 'kept' => '60.45'],
            ['month' => '2026-10', 'currency' => 'USD', 'invoices' => 1, 'collected' => '21.45', 'tax' => '1.95', 'refunded' => '0.00', 'kept' => '21.45'],
        ], $page->viewData('months')->all());

        $this->assertSame([
            ['plan' => 'Premium', 'currency' => 'EUR', 'invoices' => 1, 'collected' => '45.00', 'tax' => '0.00', 'refunded' => '0.00', 'kept' => '45.00'],
            ['plan' => 'Premium', 'currency' => 'USD', 'invoices' => 1, 'collected' => '49.00', 'tax' => '0.00', 'refunded' => '10.00', 'kept' => '39.00'],
            ['plan' => 'Standard', 'currency' => 'USD', 'invoices' => 2, 'collected' => '42.90', 'tax' => '3.90', 'refunded' => '0.00', 'kept' => '42.90'],
        ], $page->viewData('plans')->all());

        $this->assertSame(['currency' => 'USD', 'invoices' => 3, 'collected' => '91.90', 'tax' => '3.90', 'refunded' => '10.00', 'kept' => '81.90'], $page->viewData('totals')->firstWhere('currency', 'USD'));

        $open = $page->viewData('open')->firstWhere('currency', 'USD');
        $this->assertSame([2, '70.45', 1, '21.45'], [(int) $open->invoices, number_format((float) $open->owed, 2), (int) $open->overdue, number_format((float) $open->overdue_owed, 2)]);
        $this->assertSame(['IZS-INV-00005'], $page->viewData('overdue')->pluck('number')->all());
        $page->assertSeeInOrder(['Longest Overdue', 'IZS-INV-00005', 'Karim Stores', '24 days late']);

        $failed = $page->viewData('failed')->map(fn ($row) => [$row->month, (int) $row->tries, (int) $row->businesses, number_format((float) $row->amount, 2)])->all();
        $this->assertSame([['2026-11', 2, 1, '42.90'], ['2026-10', 1, 1, '49.00']], $failed);

        $credit = $page->viewData('credits')->sole();
        $this->assertSame(['15.00', '5.00', '10.00'], [number_format((float) $credit->granted, 2), number_format((float) $credit->used, 2), number_format((float) $credit->outstanding, 2)]);
    }

    public function test_it_narrows_to_the_months_currency_and_plan_chosen(): void
    {
        $this->actingAs($this->admin);

        $euros = $this->get(route('admin.billing-reports.index', ['currency' => 'EUR']))->viewData('months');
        $this->assertSame(['EUR'], $euros->pluck('currency')->unique()->values()->all());

        // Standard alone: Premium's refund is left out.
        $standard = $this->get(route('admin.billing-reports.index', ['plan' => 'Standard']))->viewData('months');
        $this->assertSame([['2026-11', '21.45', '0.00'], ['2026-10', '21.45', '0.00']], $standard->map(fn (array $row) => [$row['month'], $row['collected'], $row['refunded']])->all());

        $october = $this->get(route('admin.billing-reports.index', ['from' => '2026-10', 'to' => '2026-10']))->viewData('months');
        $this->assertSame(['2026-10'], $october->pluck('month')->all());

        $this->get(route('admin.billing-reports.index', ['from' => '2026-11', 'to' => '2026-10']))->assertSessionHasErrors('to');
    }

    public function test_only_billing_and_read_only_administrators_see_it(): void
    {
        $role = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);

        $this->actingAs($role('read-only-administrator'))->get(route('admin.billing-reports.index'))->assertOk();
        $this->actingAs($role('billing-administrator'))->get(route('admin.billing-reports.index'))->assertOk();
        $this->actingAs($role('support-administrator'))->get(route('admin.billing-reports.index'))->assertForbidden();
    }
}
