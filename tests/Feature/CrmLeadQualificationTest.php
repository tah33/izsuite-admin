<?php

namespace Tests\Feature;

use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 17 Phase B2a: a lead's qualification - need, budget, purchase period,
 * decision maker, business size, next action, notes - and the items or
 * services it is interested in, with Settings > CRM saying what must be
 * filled in before a lead is qualified.
 */
class CrmLeadQualificationTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $crm = Application::create(['app_key' => 'crm', 'name' => 'CRM', 'family_key' => 'crm', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 20, 'is_active' => true, 'status' => 'available']);
        $crm->packages()->attach(Package::pluck('id'));

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->postJson('/api/v1/apps/crm/activate')->assertOk();
    }

    private function source(): int
    {
        return $this->getJson('/api/v1/crm/leads/options')->assertOk()->json('data.sources.0.id');
    }

    private function add(array $fields = []): TestResponse
    {
        return $this->postJson('/api/v1/crm/leads', ['name' => 'Rahim Uddin', 'email' => 'rahim@example.com', 'source_id' => $this->source(), ...$fields]);
    }

    private function item(string $name): int
    {
        return $this->postJson('/api/v1/items', ['type' => 'physical', 'usage' => 'both', 'name' => $name, 'unit' => 'Piece'])->assertCreated()->json('data.id');
    }

    public function test_a_lead_keeps_its_qualification_and_interested_items(): void
    {
        $chair = $this->item('Office Chair');

        $lead = $this->add([
            'customer_need'   => 'Chairs for a new office',
            'budget'          => '5000',
            'purchase_period' => 'within_3_months',
            'decision_maker'  => 'Karim Ali, director',
            'next_action'     => 'Send a quotation',
            'interested_items' => [['item_id' => $chair, 'name' => 'ignored'], ['name' => 'Delivery and assembly', 'quantity' => '2']],
        ])->assertCreated()
            ->assertJsonPath('data.customer_need', 'Chairs for a new office')
            ->assertJsonPath('data.budget', '5000.00')
            ->assertJsonPath('data.currency', $this->business->base_currency)
            ->assertJsonPath('data.purchase_period', 'within_3_months')
            ->assertJsonPath('data.interested_items', [
                ['item_id' => $chair, 'name' => 'Office Chair', 'quantity' => null],
                ['item_id' => null, 'name' => 'Delivery and assembly', 'quantity' => '2.000'],
            ])
            ->json('data');

        $form = ['name' => 'Rahim Uddin', 'email' => 'rahim@example.com', 'source_id' => $lead['source']['id']];

        // Left out, the items stay; sent, they are replaced, and the log says so.
        $this->putJson("/api/v1/crm/leads/{$lead['id']}", [...$form, 'customer_need' => 'Chairs and desks'])->assertOk()->assertJsonCount(2, 'data.interested_items');
        $this->putJson("/api/v1/crm/leads/{$lead['id']}", [...$form, 'interested_items' => [['name' => 'Standing desk', 'quantity' => '1.5']]])->assertOk()
            ->assertJsonPath('data.interested_items', [['item_id' => null, 'name' => 'Standing desk', 'quantity' => '1.500']]);
        $this->assertSame(['interested_items'], ActivityLog::query()->where('description', 'Updated lead "Rahim Uddin"')->latest('id')->value('properties')['fields']);

        // Another business's item is not one, and a row needs an item or a name.
        $theirs = $this->businessOf(User::factory()->create())->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Theirs', 'unit' => 'Piece']);
        $this->putJson("/api/v1/crm/leads/{$lead['id']}", [...$form, 'interested_items' => [['item_id' => $theirs->id]]])->assertUnprocessable()
            ->assertJsonValidationErrors(['interested_items.0.item_id' => 'Choose one of your items, or type its name.']);
        $this->putJson("/api/v1/crm/leads/{$lead['id']}", [...$form, 'interested_items' => [['quantity' => '2']]])->assertUnprocessable()
            ->assertJsonValidationErrors(['interested_items.0.name' => 'Choose an item or type its name.']);
        $this->putJson("/api/v1/crm/leads/{$lead['id']}", [...$form, 'purchase_period' => 'someday', 'budget' => '-1'])->assertUnprocessable()
            ->assertJsonValidationErrors(['purchase_period', 'budget']);
    }

    public function test_qualifying_needs_what_settings_require(): void
    {
        $lead    = $this->add()->json('data.id');
        $qualify = fn () => $this->postJson("/api/v1/crm/leads/{$lead}/qualify");

        // To start, the customer need.
        $this->assertSame(['customer_need'], $this->getJson('/api/v1/crm/leads/options')->json('data.qualification_required'));
        $qualify()->assertUnprocessable()->assertJsonValidationErrors(['qualification' => 'Fill in the customer need before qualifying this lead.']);

        // Settings > CRM chooses, in its own order, and the log keeps the change.
        $this->putJson('/api/v1/business-settings/crm', ['qualification_required' => ['next_action', 'customer_need', 'interested_items']])->assertOk()
            ->assertJsonPath('data.qualification_required', ['customer_need', 'interested_items', 'next_action'])
            ->assertJsonPath('data.mode', 'leads');
        $this->assertSame(['qualification_required'], ActivityLog::query()->where('description', 'Updated the CRM settings')->latest('id')->value('properties')['fields']);

        $qualify()->assertUnprocessable()
            ->assertJsonValidationErrors(['qualification' => 'Fill in the customer need, the interested items and the next action before qualifying this lead.']);
        $this->getJson("/api/v1/crm/leads/{$lead}")->assertJsonPath('data.qualification_missing', ['customer_need', 'interested_items', 'next_action']);

        $this->putJson("/api/v1/crm/leads/{$lead}", [
            'name' => 'Rahim Uddin', 'email' => 'rahim@example.com', 'source_id' => $this->source(),
            'customer_need' => 'A point of sale', 'next_action' => 'Call on Monday', 'interested_items' => [['name' => 'Barcode scanner']],
        ])->assertOk()->assertJsonPath('data.qualification_missing', []);
        $qualify()->assertOk()->assertJsonPath('data.status', 'qualified');

        // None chosen, none needed; an unknown one is refused.
        $this->putJson('/api/v1/business-settings/crm', ['qualification_required' => []])->assertOk()->assertJsonPath('data.qualification_required', []);
        $this->postJson("/api/v1/crm/leads/{$this->add()->json('data.id')}/qualify")->assertOk();
        $this->putJson('/api/v1/business-settings/crm', ['qualification_required' => ['shoe_size']])->assertUnprocessable()->assertJsonValidationErrors('qualification_required.0');
    }
}
