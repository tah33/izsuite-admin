<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 16 Phase H3a: Quotation Performance, Quotation Conversion Rate and
 * Expired Quotations, on the full Sales app. Wednesday 28 October 2026; each
 * quotation is the base one, 335.50:
 *
 *   QUO-00001  sent and accepted, sold by the owner
 *   QUO-00002  sent and converted to an invoice, sold by the owner
 *   QUO-00003  sent and rejected
 *   QUO-00004  sent, with its expiry on 27 October: expired
 *   QUO-00005  sent, good until 27 November: open
 *   a draft of 20 October that expired on the 25th, and QUO-00006, sent and cancelled
 */
class QuotationReportsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $expired;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $full = Application::create(['app_key' => 'sales', 'name' => 'Sales', 'family_key' => 'sales', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 10, 'is_active' => true, 'status' => 'available']);
        $full->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->setUpSales();
    }

    private function full(): void
    {
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
    }

    /** A quotation sent - sold by the owner when $mine - and then accepted, rejected, converted or cancelled. */
    private function sent(bool $mine = false, ?string $then = null): int
    {
        $id = $this->draft('quotations', $mine ? ['salesperson_id' => $this->user->id] : []);
        $this->postJson("/api/v1/sales/quotations/{$id}/send")->assertSuccessful();

        match ($then) {
            null      => null,
            'convert' => $this->postJson("/api/v1/sales/quotations/{$id}/convert", ['to' => 'invoice'])->assertCreated(),
            default   => $this->postJson("/api/v1/sales/quotations/{$id}/{$then}", ['reason' => 'Changed their plans'])->assertSuccessful(),
        };

        return $id;
    }

    /** The story's quotations. */
    private function quote(): void
    {
        $this->full();
        $this->sent(true, 'accept');
        $this->sent(true, 'convert');
        $this->sent(false, 'reject');
        $this->expired = $this->sent();
        SalesDocument::query()->whereKey($this->expired)->update(['expiry_date' => '2026-10-27']);
        $this->sent();
        $this->draft('quotations', ['document_date' => '2026-10-20', 'expiry_date' => '2026-10-25']);
        $this->sent(false, 'cancel');
    }

    /** @return list<list<mixed>> the rows of $report, each as the values of $keys */
    private function rows(string $report, array $keys): array
    {
        return array_map(
            fn (array $row) => array_values(array_intersect_key(array_replace(array_flip($keys), $row), array_flip($keys))),
            $this->getJson("/api/v1/reports/{$report}")->assertOk()->json('data.rows'),
        );
    }

    public function test_the_three_come_with_the_full_app_for_a_role_that_sees_quotations(): void
    {
        $keys   = fn () => array_column($this->getJson('/api/v1/reports')->assertOk()->json('data'), 'key');
        $theirs = ['quotation-performance', 'quotation-conversion', 'expired-quotations'];

        $this->assertSame([], array_intersect($theirs, $keys()));

        $this->full();
        $all = $keys();
        $this->assertSame($theirs, array_slice($all, array_search('sales-by-source', $all, true) + 1, 3));

        // Seeing sales is not enough: quotations' View too.
        $member = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Analyst', 'permissions' => ['reports.view', 'sales.invoices.view', 'sales.values.view']]);
        $this->business->members()->create(['user_id' => $member->id, 'status' => 'active', 'role_id' => $role->id]);
        Sanctum::actingAs($member);

        $this->assertSame([], array_intersect($theirs, $keys()));
        $this->getJson('/api/v1/reports/expired-quotations')->assertNotFound();

        $role->forceFill(['permissions' => [...$role->permissions, 'sales.quotations.view']])->save();
        $this->assertSame($theirs, array_values(array_intersect($keys(), $theirs)));
    }

    public function test_performance_counts_each_quotation_that_went_out_where_it_stands(): void
    {
        $this->quote();

        $keys = ['group', 'quotations', 'value', 'accepted', 'converted', 'rejected', 'expired', 'open', 'won_value'];

        // The draft and the cancelled one never count.
        $this->assertSame([['Oct 2026', 5, '1677.50', 1, 1, 1, 1, 1, '671.00']], $this->rows('quotation-performance', $keys));
        $this->assertSame([
            ['No salesperson', 3, '1006.50', 0, 0, 1, 1, 1, '0.00'],
            [$this->user->name, 2, '671.00', 1, 1, 0, 0, 0, '671.00'],
        ], $this->rows('quotation-performance?group_by=salesperson', $keys));

        $this->getJson('/api/v1/reports/quotation-performance?group_by=customer')->assertOk()
            ->assertJsonPath('data.columns.0.label', 'Customer')
            ->assertJsonPath('data.rows.0.group', 'Uddin Traders');
        $this->getJson('/api/v1/reports/quotation-performance')
            ->assertJsonPath('data.totals', ['group' => 'Total', 'quotations' => 5, 'accepted' => 1, 'converted' => 1, 'rejected' => 1, 'expired' => 1, 'open' => 1, 'value' => '1677.50', 'won_value' => '671.00']);
    }

    public function test_the_conversion_rate_is_the_share_won_by_number_and_by_value(): void
    {
        $this->quote();

        $this->assertSame([
            ['No salesperson', 3, 0, '0.0', '1006.50', '0.00', '0.0'],
            [$this->user->name, 2, 2, '100.0', '671.00', '671.00', '100.0'],
        ], $this->rows('quotation-conversion?group_by=salesperson', ['group', 'quotations', 'won', 'rate', 'value', 'won_value', 'value_rate']));

        $this->getJson('/api/v1/reports/quotation-conversion')->assertOk()
            ->assertJsonPath('data.title', 'Quotation Conversion Rate')
            ->assertJsonPath('data.totals', ['group' => 'Total', 'quotations' => 5, 'won' => 2, 'rate' => '40.0', 'value' => '1677.50', 'won_value' => '671.00', 'value_rate' => '40.0']);
    }

    public function test_expired_quotations_are_those_past_their_expiry_as_they_stand(): void
    {
        $this->quote();

        $keys = ['number', 'expiry_date', 'days', 'status', 'currency', 'total', 'base_total'];

        // The latest to expire first; a draft has no number yet.
        $this->assertSame([
            ['QUO-00004', '2026-10-27', 1, 'Sent', 'USD', '335.50', '335.50'],
            ['Draft', '2026-10-25', 3, 'Draft', 'USD', '335.50', '335.50'],
        ], $this->rows('expired-quotations', $keys));

        $this->getJson('/api/v1/reports/expired-quotations')->assertOk()
            ->assertJsonPath('data.period', 'As of 28 Oct 2026')
            ->assertJsonPath('data.rows.0.customer', 'Uddin Traders')
            ->assertJsonPath('data.rows.0.link', "/sales/quotations/{$this->expired}")
            ->assertJsonPath('data.totals', ['number' => 'Total', 'base_total' => '671.00']);

        // By the 26th only the draft had expired.
        $this->assertSame([['Draft', '2026-10-25', 1, 'Draft', 'USD', '335.50', '335.50']], $this->rows('expired-quotations?as_of=2026-10-26', $keys));
    }
}
