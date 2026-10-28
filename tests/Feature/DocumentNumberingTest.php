<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Shared\ActivityLog;
use App\Models\User\DocumentNumbering;
use App\Models\User\User;
use App\Services\Documents\DocumentNumbers;
use Carbon\CarbonImmutable;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Document numbering (Doc 11 section 21, Doc 14 section 14.6): each kind of
 * document's prefix, starting number, length and period, the numbers they give
 * - never the same one twice - and the preview Settings shows.
 */
class DocumentNumberingTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const INVOICES = ['prefix' => 'INV-', 'starting_number' => 1, 'number_length' => 5, 'period' => 'none'];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
    }

    private function numbers(): DocumentNumbers
    {
        return app(DocumentNumbers::class);
    }

    private function nextInvoice(string $date = '2026-10-28'): string
    {
        return $this->numbers()->next($this->businessOf($this->user), DocumentType::Invoice, CarbonImmutable::parse($date));
    }

    private function numberInvoices(array $settings): void
    {
        $this->putJson('/api/v1/business-settings/numbering/invoice', [...self::INVOICES, ...$settings])->assertOk();
    }

    public function test_each_sales_document_starts_with_its_usual_numbering(): void
    {
        $this->assertSame(
            [
                ['quotation', 'Quotation', 'QUO-', 'QUO-00001'],
                ['proforma', 'Proforma Invoice', 'PRO-', 'PRO-00001'],
                ['invoice', 'Invoice', 'INV-', 'INV-00001'],
                ['credit_note', 'Credit Note', 'CN-', 'CN-00001'],
                ['customer_receipt', 'Customer Payment Receipt', 'RCT-', 'RCT-00001'],
            ],
            collect($this->getJson('/api/v1/business-settings/numbering')->assertOk()->json('data'))
                ->map(fn (array $row) => [$row['type'], $row['label'], $row['prefix'], $row['next_number']])
                ->all(),
        );
    }

    public function test_a_change_is_previewed_saved_and_logged(): void
    {
        $settings = ['prefix' => 'SI/', 'starting_number' => 100, 'number_length' => 4, 'period' => 'year'];

        $this->getJson('/api/v1/business-settings/numbering/invoice/preview?'.http_build_query($settings))
            ->assertOk()
            ->assertExactJson(['data' => ['next_number' => 'SI/2026-0100']]);

        $this->assertSame(0, DocumentNumbering::count());

        $this->putJson('/api/v1/business-settings/numbering/invoice', $settings)
            ->assertOk()
            ->assertJsonPath('message', 'Numbering saved successfully.')
            ->assertJsonPath('data', ['type' => 'invoice', 'label' => 'Invoice', ...$settings, 'next_number' => 'SI/2026-0100']);

        $log = ActivityLog::where('user_id', $this->user->id)->sole();
        $this->assertSame('Changed how invoices are numbered', $log->description);
        $this->assertSame(['prefix', 'starting_number', 'number_length', 'period'], $log->properties['fields']);

        // No prefix at all is a prefix too.
        $this->putJson('/api/v1/business-settings/numbering/quotation', [...self::INVOICES, 'prefix' => null])->assertJsonPath('data.next_number', '00001');
    }

    public function test_numbers_run_on_for_each_prefix_and_never_go_back(): void
    {
        $this->assertSame(['INV-00001', 'INV-00002'], [$this->nextInvoice(), $this->nextInvoice()]);

        // A new prefix starts a run of its own; the old one carries on where it stopped.
        $this->numberInvoices(['prefix' => 'SI-']);
        $this->assertSame('SI-00001', $this->nextInvoice());

        $this->numberInvoices(['prefix' => 'INV-']);
        $this->assertSame('INV-00003', $this->nextInvoice());

        // A higher starting number jumps ahead; a lower one never goes back.
        $this->numberInvoices(['starting_number' => 50]);
        $this->assertSame('INV-00050', $this->nextInvoice());

        $this->numberInvoices(['starting_number' => 10]);
        $this->assertSame('INV-00051', $this->nextInvoice());

        $this->getJson('/api/v1/business-settings/numbering')->assertJsonPath('data.2.next_number', 'INV-00052');
    }

    public function test_each_period_starts_again_from_the_first_number(): void
    {
        $this->numberInvoices(['period' => 'month']);

        $this->assertSame(
            ['INV-2026-10-00001', 'INV-2026-10-00002', 'INV-2026-11-00001'],
            [$this->nextInvoice('2026-10-01'), $this->nextInvoice('2026-10-31'), $this->nextInvoice('2026-11-01')],
        );
    }

    public function test_the_period_follows_the_business_calendar(): void
    {
        $business = $this->businessOf($this->user);
        $business->businessSetting()->create([
            'user_id' => $this->user->id, 'business_name' => 'Uddin Traders', 'language' => 'en', 'country' => 'Bangladesh', 'state' => 'Dhaka',
            'city' => 'Dhaka', 'zip_code' => '1207', 'landmark' => 'Near City Center', 'timezone' => 'Asia/Dhaka',
            'financial_year_start_month' => 7, 'stock_accounting_method' => 'fifo',
        ]);

        $preview = fn (string $period, string $date) => $this->numbers()->preview(
            $business,
            DocumentNumbering::defaultsFor(DocumentType::Invoice)->fill(['period' => $period]),
            CarbonImmutable::parse($date),
        );

        // A financial year from July: March 2026 is in 2025-26, July in 2026-27.
        $this->assertSame('INV-2026-00001', $preview('year', '2026-03-15'));
        $this->assertSame('INV-2025-26-00001', $preview('financial_year', '2026-03-15'));
        $this->assertSame('INV-2026-27-00001', $preview('financial_year', '2026-07-01'));

        // "Today" is the business's: 20:00 UTC on New Year's Eve is already 2027 in Dhaka.
        $this->travelTo(Carbon::parse('2026-12-31 20:00:00', 'UTC'));
        $this->putJson('/api/v1/business-settings/numbering/invoice', [...self::INVOICES, 'period' => 'year'])->assertJsonPath('data.next_number', 'INV-2027-00001');
    }

    public function test_a_number_already_on_a_document_is_skipped(): void
    {
        $taken = ['INV-00001', 'INV-00002'];

        $this->assertSame('INV-00003', $this->numbers()->next(
            $this->businessOf($this->user),
            DocumentType::Invoice,
            CarbonImmutable::parse('2026-10-28'),
            fn (string $number) => in_array($number, $taken, true),
        ));
    }

    public function test_settings_that_cannot_number_documents_are_refused(): void
    {
        $url = '/api/v1/business-settings/numbering/invoice';

        $this->putJson($url, [...self::INVOICES, 'prefix' => 'INV 2026'])->assertJsonValidationErrors(['prefix' => 'A prefix uses letters, numbers and # / . _ - only, such as INV-.']);
        $this->putJson($url, [...self::INVOICES, 'prefix' => str_repeat('A', 21)])->assertJsonValidationErrors('prefix');
        $this->putJson($url, [...self::INVOICES, 'number_length' => 11])->assertJsonValidationErrors(['number_length' => 'A number has 1 to 10 digits.']);
        $this->putJson($url, [...self::INVOICES, 'starting_number' => 0])->assertJsonValidationErrors(['starting_number' => 'Numbers start at 1 or above.']);
        $this->putJson($url, [...self::INVOICES, 'period' => 'week'])->assertJsonValidationErrors('period');

        $this->assertSame(0, DocumentNumbering::count());
    }

    public function test_only_the_documents_of_apps_that_are_on_are_numbered_here(): void
    {
        $this->putJson('/api/v1/business-settings/numbering/purchase', self::INVOICES)->assertNotFound();

        $this->businessOf($this->user)->workspaceApps()->delete();

        $this->assertSame([], $this->getJson('/api/v1/business-settings/numbering')->json('data'));
        $this->putJson('/api/v1/business-settings/numbering/invoice', self::INVOICES)
            ->assertNotFound()
            ->assertJsonPath('message', 'There is no such kind of document.');
    }
}
