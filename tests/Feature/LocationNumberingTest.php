<?php

namespace Tests\Feature;

use App\Models\User\Location;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Document numbers with the location's code (Doc 11 section 21, Doc 14
 * section 14.6) - Doc 11 Phase D: a type numbered that way runs on per
 * location, a record without one taking the Primary's code; off, the type
 * carries on its own run.
 */
class LocationNumberingTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Location $main;

    private Location $dhaka;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();

        $business    = $this->businessOf($this->user);
        $this->main  = $business->locations()->create(['user_id' => $this->user->id, 'name' => 'Main Location', 'code' => 'MAIN', 'is_primary' => true, 'timezone' => 'UTC']);
        $this->dhaka = $business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka Shop', 'code' => 'DHK', 'timezone' => 'Asia/Dhaka']);
    }

    private function numbering(bool $locationCode): array
    {
        return ['prefix' => 'INV-', 'starting_number' => 1, 'number_length' => 5, 'period' => 'none', 'location_code' => $locationCode];
    }

    private function numberAt(Location $location): string
    {
        return $this->getJson('/api/v1/sales/invoices/'.$this->issuedInvoice(['location_id' => $location->id]))
            ->assertJsonPath('data.location_id', $location->id)
            ->json('data.number');
    }

    public function test_numbers_with_the_location_code_run_on_per_location(): void
    {
        $this->assertSame('INV-00001', $this->numberAt($this->main));

        $this->putJson('/api/v1/business-settings/numbering/invoice', $this->numbering(true))
            ->assertOk()
            ->assertJsonPath('data.location_code', true)
            ->assertJsonPath('data.next_number', 'INV-MAIN-00001');

        $this->assertSame('INV-MAIN-00001', $this->numberAt($this->main));
        $this->assertSame('INV-DHK-00001', $this->numberAt($this->dhaka));
        $this->assertSame('INV-MAIN-00002', $this->numberAt($this->main));

        // Off again, the type carries on its own run; the numbers given stay.
        $this->putJson('/api/v1/business-settings/numbering/invoice', $this->numbering(false))->assertJsonPath('data.next_number', 'INV-00002');
        $this->assertSame('INV-00002', $this->numberAt($this->dhaka));
    }

    public function test_the_list_and_a_preview_show_the_primary_location_code(): void
    {
        $this->getJson('/api/v1/business-settings/numbering/invoice/preview?'.http_build_query([...$this->numbering(true), 'location_code' => 1]))
            ->assertOk()
            ->assertJsonPath('data.next_number', 'INV-MAIN-00001');

        $rows = collect($this->getJson('/api/v1/business-settings/numbering')->assertOk()->json('data'))->keyBy('type');

        $this->assertFalse($rows['invoice']['location_code']);
        $this->assertSame('INV-00001', $rows['invoice']['next_number']);
    }
}
