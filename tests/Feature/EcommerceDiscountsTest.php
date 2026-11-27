<?php

namespace Tests\Feature;

use App\Models\User\EcommerceDiscount;
use App\Models\User\EcommerceOrder;
use App\Models\User\User;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * The Discounts page (Doc 19 section 19.31 - Phase H1), under Manage
 * Discounts: discount codes added and changed - their codes in capitals, no
 * two alike - for the whole order or chosen products, with their minimum,
 * days and limits; deleted, or deactivated once used on an order.
 */
class EcommerceDiscountsTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const DISCOUNTS = '/api/v1/ecommerce/discounts';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-27 10:00:00');
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00'], 'Tea Towel' => ['sale_price' => '3.00']]);
        $this->listed('Ceramic Mug');
        $this->listed('Tea Towel');
    }

    private function fields(array $over = []): array
    {
        return ['name' => 'Summer Sale', 'code' => ' summer10 ', 'kind' => 'percentage', 'value' => 10, 'applies_to' => 'order', ...$over];
    }

    public function test_discount_codes_are_added_changed_and_searched(): void
    {
        $this->postJson(self::DISCOUNTS, $this->fields(['minimum' => '20', 'starts_on' => '2026-11-27', 'ends_on' => '2026-12-31', 'usage_limit' => 100, 'per_customer_limit' => 1]))->assertCreated()
            ->assertJsonPath('message', 'Discount SUMMER10 added.')
            ->assertJsonPath('data.code', 'SUMMER10')
            ->assertJsonPath('data.value', '10')
            ->assertJsonPath('data.minimum', '20.00')
            ->assertJsonPath('data.uses', 0)
            ->assertJsonPath('data.state', 'active');

        // No two alike, and each field as it should be.
        $this->postJson(self::DISCOUNTS, $this->fields(['code' => 'Summer10']))->assertUnprocessable()->assertJsonPath('errors.code.0', 'Another of your discounts has this code: choose another.');
        $this->postJson(self::DISCOUNTS, $this->fields(['code' => 'SUM MER']))->assertUnprocessable()->assertJsonPath('errors.code.0', 'Use letters, digits and dashes.');
        $this->postJson(self::DISCOUNTS, $this->fields(['code' => 'BIG', 'value' => 120]))->assertUnprocessable()->assertJsonPath('errors.value.0', 'Take off 100% at most.');
        $this->postJson(self::DISCOUNTS, $this->fields(['code' => 'MUGS', 'applies_to' => 'products']))->assertUnprocessable()->assertJsonPath('errors.product_ids.0', 'Choose the products it is for.');
        $this->postJson(self::DISCOUNTS, $this->fields(['code' => 'MUGS', 'applies_to' => 'products', 'product_ids' => [99999]]))->assertUnprocessable()->assertJsonPath('errors.product_ids.0', 'Choose products of your own catalog.');
        $this->postJson(self::DISCOUNTS, $this->fields(['code' => 'LATE', 'starts_on' => '2026-12-01', 'ends_on' => '2026-11-30']))->assertUnprocessable()->assertJsonPath('errors.ends_on.0', 'End it on its first day or after.');

        // For chosen products, picked from the catalog; free shipping takes no value.
        $mug = collect($this->getJson(self::DISCOUNTS.'/products?search=mug')->assertOk()->json('data'))->sole();
        $this->assertSame('Ceramic Mug', $mug['title']);
        $this->postJson(self::DISCOUNTS, $this->fields(['name' => 'Mugs', 'code' => 'mugs', 'kind' => 'amount', 'value' => '2.50', 'applies_to' => 'products', 'product_ids' => [$mug['id']]]))->assertCreated()
            ->assertJsonPath('data.products', [['id' => $mug['id'], 'title' => 'Ceramic Mug']]);
        $this->postJson(self::DISCOUNTS, $this->fields(['name' => 'Free Delivery', 'code' => 'shipfree', 'kind' => 'free_shipping', 'value' => 5]))->assertCreated()->assertJsonPath('data.value', null);

        $this->getJson(self::DISCOUNTS.'?search=summer')->assertOk()->assertJsonPath('data.*.code', ['SUMMER10'])->assertJsonPath('pagination.total', 1);
        $summer = EcommerceDiscount::where('code', 'SUMMER10')->sole();
        $this->putJson(self::DISCOUNTS."/{$summer->id}", $this->fields(['value' => 15, 'is_active' => false]))->assertOk()
            ->assertJsonPath('message', 'Discount SUMMER10 saved.')
            ->assertJsonPath('data.state', 'inactive');
    }

    public function test_an_automatic_discount_has_no_code_and_each_a_priority_and_whether_it_goes_with_others(): void
    {
        // Phase H2a: given automatically, a code sent is not kept; a priority of 1 and alone to start.
        $this->postJson(self::DISCOUNTS, $this->fields(['name' => 'Weekend Sale', 'automatic' => true]))->assertCreated()
            ->assertJsonPath('message', 'Discount Weekend Sale added.')
            ->assertJsonPath('data.code', null)
            ->assertJsonPath('data.automatic', true)
            ->assertJsonPath('data.priority', 1)
            ->assertJsonPath('data.combines', false);
        $this->postJson(self::DISCOUNTS, $this->fields(['name' => 'Members', 'automatic' => true, 'priority' => 2, 'combines' => true]))->assertCreated()
            ->assertJsonPath('data.priority', 2)
            ->assertJsonPath('data.combines', true);

        // A code is still needed when it is not automatic; a priority from 1 to 99.
        $this->postJson(self::DISCOUNTS, $this->fields(['code' => null]))->assertUnprocessable()->assertJsonPath('errors.code.0', 'Give its code: what customers type at checkout.');
        $this->postJson(self::DISCOUNTS, $this->fields(['priority' => 0]))->assertUnprocessable()->assertJsonPath('errors.priority.0', 'Give a priority from 1 to 99: 1 is taken first.');

        // Searched by its name; made a code, and back.
        $weekend = EcommerceDiscount::where('name', 'Weekend Sale')->sole();
        $this->getJson(self::DISCOUNTS.'?search=weekend')->assertOk()->assertJsonPath('data.*.name', ['Weekend Sale']);
        $this->putJson(self::DISCOUNTS."/{$weekend->id}", $this->fields(['name' => 'Weekend Sale', 'code' => 'weekend']))->assertOk()->assertJsonPath('data.code', 'WEEKEND')->assertJsonPath('data.automatic', false);
        $this->putJson(self::DISCOUNTS."/{$weekend->id}", $this->fields(['name' => 'Weekend Sale', 'automatic' => true]))->assertOk()
            ->assertJsonPath('message', 'Discount Weekend Sale saved.')
            ->assertJsonPath('data.code', null);
        $this->assertDatabaseHas('activity_logs', ['description' => 'Changed the discount Weekend Sale (automatic)']);
    }

    public function test_a_discount_is_deleted_until_used_then_deactivated(): void
    {
        $unused = $this->postJson(self::DISCOUNTS, $this->fields())->json('data.id');
        $this->deleteJson(self::DISCOUNTS."/{$unused}")->assertOk()->assertJsonPath('message', 'Discount deleted.');
        $this->assertNull(EcommerceDiscount::find($unused));

        $used = $this->postJson(self::DISCOUNTS, $this->fields(['code' => 'USED']))->json('data.id');
        $this->setUpCheckout();
        // A mug ($8) shipped by Standard ($5), 10% off its products: $12.20.
        $this->place($this->cartWith(['ceramic-mug' => 1]), $this->order('12.20', ['discount_code' => 'used']))->assertCreated();
        Sanctum::actingAs($this->user);
        $this->assertSame($used, EcommerceOrder::sole()->promotions()->value('ecommerce_discount_id'));

        $this->deleteJson(self::DISCOUNTS."/{$used}")->assertOk()->assertJsonPath('message', 'The discount was used on orders, so it is kept with them: it is deactivated instead.');
        $this->assertFalse(EcommerceDiscount::find($used)->is_active);
    }

    public function test_discounts_need_manage_discounts(): void
    {
        $this->assertContains('ecommerce.store.view', Permissions::expand(['ecommerce.discounts.manage']));
        $this->assertContains('ecommerce.discounts.manage', Permissions::forTemplate('manager', ['sales', 'ecommerce']));

        $user   = User::factory()->create();
        $role   = $this->business->roles()->create(['name' => 'Orders only', 'permissions' => ['ecommerce.orders.view']]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);
        Sanctum::actingAs($user);

        $this->getJson(self::DISCOUNTS)->assertForbidden();
        $this->postJson(self::DISCOUNTS, $this->fields())->assertForbidden();
    }
}
