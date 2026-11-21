<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Location;
use App\Models\User\ShippingMethod;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Services\Api\Ecommerce\ShippingRates;
use App\Support\Permissions;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 19 Phase D1: shipping and pickup (section 19.26) in Settings →
 * eCommerce, under Manage Shipping - zones and their methods, and the
 * business's locations as pickup points - and what an address and an order
 * are offered by them (ShippingRates).
 */
class EcommerceShippingTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsIntoBusinesses;

    private const SHIPPING = '/api/v1/business-settings/ecommerce/shipping';

    private const ZONES = '/api/v1/business-settings/ecommerce/shipping-zones';

    private const METHODS = '/api/v1/business-settings/ecommerce/shipping-methods';

    protected function setUp(): void
    {
        parent::setUp();

        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00']]);
    }

    private function zone(string $name, array $fields = []): int
    {
        return $this->postJson(self::ZONES, ['name' => $name, 'countries' => ['BD'], ...$fields])->assertCreated()->json('data.id');
    }

    private function method(int $zone, string $name, array $fields): int
    {
        return $this->postJson(self::METHODS, ['shipping_zone_id' => $zone, 'name' => $name, ...$fields])->assertCreated()->json('data.id');
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => $permissions]);
        $this->business->members()->create(['user_id' => $user->id, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    public function test_zones_and_their_methods_are_kept_as_given(): void
    {
        $this->getJson(self::SHIPPING)->assertOk()
            ->assertJsonPath('data.zones', [])
            ->assertJsonPath('data.tax_rates', null)
            ->assertJsonPath('data.categories', [['id' => $this->kitchen->id, 'name' => 'Kitchen']])
            ->assertJsonPath('data.currency', 'USD');

        $this->postJson(self::ZONES, ['name' => ' Dhaka City ', 'countries' => ['BD', 'BD'], 'cities' => [' Dhaka ', 'dhaka', ''], 'postal_codes' => ['12*', ' sw1a  1aa ']])->assertCreated()
            ->assertJsonPath('message', 'Shipping zone added.')
            ->assertJsonPath('data.name', 'Dhaka City')
            ->assertJsonPath('data.countries', ['BD'])
            ->assertJsonPath('data.regions', [])
            ->assertJsonPath('data.cities', ['Dhaka'])
            ->assertJsonPath('data.postal_codes', ['12*', 'SW1A 1AA'])
            ->assertJsonPath('data.position', 1);

        $this->postJson(self::ZONES, ['name' => 'Dhaka City', 'countries' => ['BD']])->assertJsonValidationErrors(['name' => 'Another shipping zone has this name.']);
        $this->postJson(self::ZONES, ['name' => 'Nowhere', 'countries' => []])->assertJsonValidationErrors(['countries' => 'Choose the countries it covers.']);
        $this->postJson(self::ZONES, ['name' => 'Odd', 'countries' => ['BD'], 'postal_codes' => ['*12']])->assertJsonValidationErrors('postal_codes.0');

        $country = $this->zone('Bangladesh');
        $this->assertSame(2, $this->getJson(self::SHIPPING)->json('data.zones.1.position'));

        // A flat rate with its delivery days.
        $this->postJson(self::METHODS, ['shipping_zone_id' => $country, 'name' => 'Standard', 'kind' => 'flat', 'price' => '5', 'days_min' => 2, 'days_max' => 4])->assertCreated()
            ->assertJsonPath('message', 'Shipping method added.')
            ->assertJsonPath('data.price', '5.00')
            ->assertJsonPath('data.rates', null)
            ->assertJsonPath('data.is_active', true);

        // Bands, kept from the lowest, each start once.
        $this->postJson(self::METHODS, ['shipping_zone_id' => $country, 'name' => 'Heavy', 'kind' => 'weight'])->assertJsonValidationErrors(['rates' => 'Give at least one rate.']);
        $this->postJson(self::METHODS, ['shipping_zone_id' => $country, 'name' => 'Heavy', 'kind' => 'weight', 'rates' => [['from' => 2, 'price' => 6], ['from' => '2.0', 'price' => 7]]])
            ->assertJsonValidationErrors(['rates' => 'Two rates start at 2: give each its own start.']);
        $heavy = $this->postJson(self::METHODS, ['shipping_zone_id' => $country, 'name' => 'Heavy', 'kind' => 'weight', 'price' => '99', 'rates' => [['from' => 5, 'price' => 12], ['from' => 0, 'price' => 6]]])->assertCreated()
            ->assertJsonPath('data.price', null)
            ->assertJsonPath('data.rates', [['from' => '0.000', 'price' => '6.00'], ['from' => '5.000', 'price' => '12.00']])
            ->json('data.id');

        $this->postJson(self::METHODS, ['shipping_zone_id' => $country, 'name' => 'Standard', 'kind' => 'free'])->assertJsonValidationErrors(['name' => 'Another method of Bangladesh has this name.']);
        $this->postJson(self::METHODS, ['shipping_zone_id' => $country, 'name' => 'Flat', 'kind' => 'flat'])->assertJsonValidationErrors(['price' => 'Give its price.']);
        $this->postJson(self::METHODS, ['shipping_zone_id' => $country, 'name' => 'Free', 'kind' => 'free', 'min_order' => 50, 'max_order' => 20])->assertJsonValidationErrors(['max_order' => 'The highest order value is below the lowest.']);
        $this->postJson(self::METHODS, ['shipping_zone_id' => $country, 'name' => 'Free', 'kind' => 'free', 'category_ids' => [999999]])->assertJsonValidationErrors(['category_ids' => 'Choose among your item categories.']);
        // A tax while the business has taxes off is not kept.
        $this->postJson(self::METHODS, ['shipping_zone_id' => $country, 'name' => 'Free', 'kind' => 'free', 'min_order' => 50, 'tax_rate_id' => 1, 'category_ids' => [$this->kitchen->id]])->assertCreated()
            ->assertJsonPath('data.tax_rate', null)
            ->assertJsonPath('data.category_ids', [$this->kitchen->id]);

        $this->putJson(self::METHODS.'/'.$heavy, ['shipping_zone_id' => $country, 'name' => 'By Weight', 'kind' => 'weight', 'rates' => [['from' => 0, 'price' => 7]], 'max_weight' => 30, 'is_active' => false])->assertOk()
            ->assertJsonPath('message', 'Shipping method saved.')
            ->assertJsonPath('data.name', 'By Weight')
            ->assertJsonPath('data.max_weight', '30.000')
            ->assertJsonPath('data.is_active', false);

        $this->assertSame(['Standard', 'By Weight', 'Free'], collect($this->getJson(self::SHIPPING)->json('data.zones.1.methods'))->pluck('name')->all());

        // Deleted, a zone takes its methods with it.
        $this->deleteJson(self::ZONES.'/'.$country)->assertOk()->assertJsonPath('message', 'Shipping zone deleted.');
        $this->assertSame(0, ShippingMethod::count());
        $this->assertTrue(ActivityLog::where('description', 'Deleted the shipping zone "Bangladesh" and its methods')->exists());
        $this->assertTrue(ActivityLog::where('description', 'Updated the shipping method "By Weight" of Bangladesh')->exists());
    }

    public function test_a_method_takes_a_sales_tax_rate_that_is_on_while_taxes_are_on(): void
    {
        $vat = $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT10', 'rate' => '10', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01']);
        $old = $this->business->taxRates()->create(['name' => 'Old', 'code' => 'OLD', 'rate' => '5', 'treatment' => 'standard', 'for_sales' => true, 'is_active' => false, 'effective_from' => '2026-01-01']);
        $this->business->forceFill(['tax_mode' => Workspace::TAXES_ENABLED])->save();
        $zone = $this->zone('Bangladesh');

        $this->getJson(self::SHIPPING)->assertJsonPath('data.tax_rates', [['id' => $vat->id, 'name' => 'VAT', 'rate' => '10.0000']]);
        $this->postJson(self::METHODS, ['shipping_zone_id' => $zone, 'name' => 'Standard', 'kind' => 'flat', 'price' => 5, 'tax_rate_id' => $old->id])
            ->assertJsonValidationErrors(['tax_rate_id' => 'Choose one of your sales tax rates that is on.']);
        $this->postJson(self::METHODS, ['shipping_zone_id' => $zone, 'name' => 'Standard', 'kind' => 'flat', 'price' => 5, 'tax_rate_id' => $vat->id])->assertCreated()
            ->assertJsonPath('data.tax_rate.name', 'VAT');
    }

    public function test_an_address_takes_the_first_zone_it_matches_and_its_methods_that_take_the_order(): void
    {
        $city    = $this->zone('Dhaka City', ['cities' => ['Dhaka'], 'postal_codes' => ['12*']]);
        $country = $this->zone('Bangladesh');
        $this->zone('India', ['countries' => ['IN']]);

        $this->method($city, 'Local Delivery', ['kind' => 'local_delivery', 'price' => '2.50']);
        $this->method($city, 'Free Over 50', ['kind' => 'free', 'min_order' => 50]);
        $this->method($country, 'Standard', ['kind' => 'flat', 'price' => 5]);
        $this->method($country, 'By Weight', ['kind' => 'weight', 'rates' => [['from' => 0, 'price' => 6], ['from' => 5, 'price' => 12]], 'max_weight' => 20]);
        $this->method($country, 'Kitchen by Quantity', ['kind' => 'quantity', 'rates' => [['from' => 1, 'price' => 3], ['from' => 10, 'price' => 2]], 'category_ids' => [$this->kitchen->id]]);
        $this->method($country, 'Quoted', ['kind' => 'quotation']);
        $this->method($country, 'Off', ['kind' => 'flat', 'price' => 1, 'is_active' => false]);

        $rates   = app(ShippingRates::class);
        $kitchen = $this->kitchen->id;
        $offered = function (array $address, array $parcel) use ($rates): array {
            $found = $rates->for($this->business, $address, ['subtotal' => '30.00', 'quantity' => '2', 'weight' => '6', 'categories' => [$this->kitchen->id], ...$parcel]);

            return [$found['zone']?->name, collect($found['methods'])->mapWithKeys(fn (array $offer) => [$offer['method']->name => $offer['price']])->all()];
        };

        // In Dhaka by its postal code - whatever the city's case - the first zone: free from an order of 50.
        $this->assertSame(['Dhaka City', ['Local Delivery' => '2.50']], $offered(['country' => 'bd', 'city' => ' DHAKA ', 'postal_code' => '1207'], []));
        $this->assertSame(['Dhaka City', ['Local Delivery' => '2.50', 'Free Over 50' => '0.00']], $offered(['country' => 'BD', 'city' => 'Dhaka', 'postal_code' => '12 07'], ['subtotal' => '50.00']));

        // Outside its postal codes, or another city: the country's zone - the band the weight reaches, a quotation unpriced, and not one that is off.
        $this->assertSame(
            ['Bangladesh', ['Standard' => '5.00', 'By Weight' => '12.00', 'Kitchen by Quantity' => '3.00', 'Quoted' => null]],
            $offered(['country' => 'BD', 'city' => 'Dhaka', 'postal_code' => '1000'], []),
        );

        // Too heavy for By Weight; something of no category for the kitchen's; too few for its first band.
        $this->assertSame(['Standard', 'Kitchen by Quantity', 'Quoted'], array_keys($offered(['country' => 'BD', 'city' => 'Sylhet'], ['weight' => '25'])[1]));
        $this->assertSame(['Standard', 'By Weight', 'Quoted'], array_keys($offered(['country' => 'BD', 'city' => 'Sylhet'], ['categories' => [$kitchen, null]])[1]));
        $this->assertSame(['Standard', 'By Weight', 'Quoted'], array_keys($offered(['country' => 'BD', 'city' => 'Sylhet'], ['quantity' => '0.5'])[1]));
        $this->assertSame('2.00', $offered(['country' => 'BD', 'city' => 'Sylhet'], ['quantity' => '12'])[1]['Kitchen by Quantity']);

        // No zone of its country: nothing offered.
        $this->assertSame([null, []], $offered(['country' => 'US', 'city' => 'Boston'], []));

        $this->assertSame(0, bccomp('0.5', ShippingRates::kilograms('500', 'g'), 6));
        $this->assertSame(0, bccomp('0.453592', ShippingRates::kilograms('1', 'lb'), 6));
    }

    public function test_customers_may_collect_at_the_locations_switched_on_for_pickup(): void
    {
        $dhaka   = Location::where('code', 'DHK')->sole();
        $closed  = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Old Depot', 'code' => 'OLD', 'is_active' => false]);
        $pickups = '/api/v1/business-settings/ecommerce/pickup-points/';

        $this->assertContains('Dhaka', collect($this->getJson(self::SHIPPING)->json('data.pickup'))->pluck('name')->all());
        $this->assertNotContains('Old Depot', collect($this->getJson(self::SHIPPING)->json('data.pickup'))->pluck('name')->all());

        $this->putJson($pickups.$dhaka->id, ['is_active' => true, 'instructions' => ' Ask at the front desk. ', 'ready_within' => 'Ready in 2 hours'])->assertOk()
            ->assertJsonPath('message', 'Pickup saved.')
            ->assertJsonPath('data.offered', true)
            ->assertJsonPath('data.instructions', 'Ask at the front desk.')
            ->assertJsonPath('data.ready_within', 'Ready in 2 hours');

        $this->putJson($pickups.$closed->id, ['is_active' => true])->assertJsonValidationErrors(['is_active' => 'Old Depot is inactive: make it active in Settings → Locations first.']);
        $this->assertSame(['Dhaka'], app(\App\Repositories\User\ShippingRepository::class)->offeredPickupPoints($this->business)->map(fn ($point) => $point->location->name)->all());

        $this->putJson($pickups.$dhaka->id, ['is_active' => false, 'instructions' => 'Ask at the front desk.'])->assertOk()
            ->assertJsonPath('message', 'Pickup switched off.')
            ->assertJsonPath('data.offered', false)
            ->assertJsonPath('data.instructions', 'Ask at the front desk.');
        $this->assertSame([], app(\App\Repositories\User\ShippingRepository::class)->offeredPickupPoints($this->business)->all());
        $this->assertTrue(ActivityLog::where('description', 'Offered pickup at Dhaka')->exists());
        $this->assertTrue(ActivityLog::where('description', 'Stopped offering pickup at Dhaka')->exists());
    }

    public function test_shipping_is_changed_with_manage_shipping(): void
    {
        $zone = $this->zone('Bangladesh');

        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.store.view', 'ecommerce.settings.manage'])));
        $this->getJson(self::SHIPPING)->assertForbidden();
        $this->postJson(self::ZONES, ['name' => 'India', 'countries' => ['IN']])->assertForbidden();

        Sanctum::actingAs($this->member(Permissions::expand(['ecommerce.shipping.manage'])));
        $this->getJson(self::SHIPPING)->assertOk()->assertJsonPath('data.zones.0.name', 'Bangladesh');
        $this->method($zone, 'Standard', ['kind' => 'flat', 'price' => 5]);
        $this->assertEqualsCanonicalizing(['settings.view', 'ecommerce.store.view', 'ecommerce.shipping.manage'], Permissions::expand(['ecommerce.shipping.manage']));

        // Another business's zone is not found.
        $this->putJson(self::ZONES.'/999999', ['name' => 'X', 'countries' => ['BD']])->assertNotFound();
    }
}
