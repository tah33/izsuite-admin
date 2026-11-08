<?php

namespace Tests\Feature;

use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * What is still open in an app (Doc 13 section 8) - Doc 13 Phase A: before
 * Sales Lite goes off, the drafts not yet issued and the invoices not yet
 * paid are counted, so nobody loses track of them.
 */
class AppOpenItemsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->setUpSales();
    }

    public function test_turning_sales_off_counts_its_drafts_and_unpaid_invoices(): void
    {
        $this->assertSame([], $this->getJson('/api/v1/apps/sales_lite/deactivation')->assertOk()->json('data.open_items'));

        $this->issuedInvoice();
        $this->issuedInvoice();
        $this->draft('quotations');

        $this->getJson('/api/v1/apps/sales_lite/deactivation')->assertOk()->assertJsonPath('data.open_items', [
            ['label' => 'Drafts not yet issued', 'count' => 1],
            ['label' => 'Unpaid invoices', 'count' => 2],
        ]);
    }
}
