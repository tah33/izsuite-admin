<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase F2: an opportunity won when a sales order made from it is
 * confirmed, while Settings → CRM says so - today, at the order's value
 * before tax when they share a currency, by whoever confirmed it - and left
 * as it is otherwise. Monday 16 November 2026, the full Sales app; the
 * customer is MakesSalesDocuments' Uddin Traders, and the order its 295.00
 * before tax.
 */
class CrmOpportunityOrderWinTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    private int $opportunity;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);

        $this->offerSalesLite();
        $category = Application::sole()->category_id;
        foreach ([['sales', 'Sales', 'sales', 10], ['crm', 'CRM', 'crm', 20]] as [$key, $name, $family, $order]) {
            Application::create(['app_key' => $key, 'name' => $name, 'family_key' => $family, 'edition' => 'full', 'category_id' => $category, 'sort_order' => $order, 'is_active' => true, 'status' => 'available'])
                ->packages()->attach(Package::pluck('id'));
        }

        $this->travelTo(Carbon::parse('2026-11-16 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->save();
        $this->business->exchangeRates()->create(['currency' => 'EUR', 'base_currency' => 'USD', 'rate' => '1.08', 'effective_date' => '2026-10-01']);
        $this->setUpSales();
        $this->postJson('/api/v1/apps/sales/activate')->assertOk();
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();

        $this->opportunity = $this->addOpportunity();
    }

    private function addOpportunity(array $fields = []): int
    {
        $stage = $this->getJson('/api/v1/business-settings/crm')->json('data.pipelines.0.stages.0.id');

        return $this->postJson('/api/v1/crm/opportunities', [
            'name' => 'Uddin Traders - chairs', 'contact_id' => $this->customer->id, 'stage_id' => $stage, 'expected_value' => '2000', 'currency' => 'USD', 'expected_close_date' => '2026-12-20', ...$fields,
        ])->assertCreated()->json('data.id');
    }

    /** A draft sales order made from $opportunity - its id. */
    private function order(int $opportunity): int
    {
        return $this->draft('orders', ['opportunity_id' => $opportunity]);
    }

    private function opportunity(int $id): TestResponse
    {
        return $this->getJson("/api/v1/crm/opportunities/{$id}")->assertOk();
    }

    public function test_confirming_an_order_made_from_an_opportunity_wins_it_at_the_orders_value_before_tax(): void
    {
        $this->getJson('/api/v1/business-settings/crm')->assertJsonPath('data.win_on_order', true);
        $order = $this->order($this->opportunity);
        $this->opportunity($this->opportunity)->assertJsonPath('data.status', 'open');

        $this->travel(1)->hours();
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');

        $won = $this->opportunity($this->opportunity)->json('data');
        $this->assertSame(['won', 'Won', 100, '295.00'], [$won['status'], $won['stage']['name'], $won['probability'], $won['expected_value']]);
        $this->assertSame(['on' => '2026-11-16', 'by' => $this->user->name, 'won_reason' => 'Sales order SO-00001 confirmed'], array_intersect_key($won['outcome'], array_flip(['on', 'by', 'won_reason'])));

        // On its timeline: the win, marked so, and the order's confirmation by whom.
        $changes = $this->getJson("/api/v1/crm/timeline/opportunity/{$this->opportunity}?show=changes")->json('data');
        $this->assertSame(['Marked opportunity "Uddin Traders - chairs" won: sales order SO-00001 was confirmed', 'won', $this->user->name], [$changes[0]['text'], $changes[0]['mark'], $changes[0]['by']]);
        $this->assertSame(['value', 'won_reason'], array_slice(array_column($changes[0]['changes'], 'setting'), -2));
        $documents = $this->getJson("/api/v1/crm/timeline/opportunity/{$this->opportunity}?show=documents")->json('data');
        $this->assertSame([['confirmed', $this->user->name], ['made', $this->user->name]], array_map(fn (array $entry) => [$entry['step'], $entry['by']], $documents));

        // Whoever confirms it wins it, whether or not they see the opportunity; in another currency its value stays its own.
        $euros = $this->addOpportunity(['name' => 'Uddin Traders - desks', 'currency' => 'EUR']);
        $order = $this->order($euros);
        $clerk = User::factory()->create();
        $role  = $this->business->roles()->create(['name' => 'Order Clerk', 'permissions' => ['sales.orders.view', 'sales.orders.confirm']]);
        $this->business->members()->create(['user_id' => $clerk->id, 'name' => $clerk->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        Sanctum::actingAs($clerk);
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk();
        Sanctum::actingAs($this->user);
        $this->assertSame(['won', '2000.00', $clerk->name], [$this->opportunity($euros)->json('data.status'), $this->opportunity($euros)->json('data.expected_value'), $this->opportunity($euros)->json('data.outcome.by')]);
    }

    public function test_it_is_left_as_it_is_when_settings_say_not_or_it_is_no_longer_open(): void
    {
        // Switched off in Settings → CRM.
        $this->putJson('/api/v1/business-settings/crm', ['win_on_order' => false])->assertOk()->assertJsonPath('data.win_on_order', false);
        $this->postJson('/api/v1/sales/orders/'.$this->order($this->opportunity).'/confirm')->assertOk();
        $this->opportunity($this->opportunity)->assertJsonPath('data.status', 'open');
        $this->putJson('/api/v1/business-settings/crm', ['win_on_order' => true])->assertOk();

        // Lost after its order was made: it stays lost, and the order is confirmed all the same.
        $order  = $this->order($this->opportunity);
        $reason = $this->getJson('/api/v1/crm/opportunities/options')->json('data.lost_reasons.0.id');
        $this->postJson("/api/v1/crm/opportunities/{$this->opportunity}/lose", ['closed_on' => '2026-11-16', 'lost_reason_id' => $reason])->assertOk();
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk()->assertJsonPath('data.status', 'confirmed');
        $this->opportunity($this->opportunity)->assertJsonPath('data.status', 'lost');

        // An order of no opportunity, or with CRM off, wins nothing.
        $other = $this->addOpportunity(['name' => 'Uddin Traders - shelves']);
        $this->postJson('/api/v1/sales/orders/'.$this->draft('orders').'/confirm')->assertOk();
        $order = $this->order($other);
        $this->postJson('/api/v1/apps/crm/deactivate')->assertOk();
        $this->postJson("/api/v1/sales/orders/{$order}/confirm")->assertOk();
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
        $this->opportunity($other)->assertJsonPath('data.status', 'open');
    }
}
