<?php

namespace Tests\Feature;

use App\Enums\DocumentType;
use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Models\User\DocumentTemplate;
use App\Models\User\ExchangeRate;
use App\Models\User\User;
use App\Repositories\User\DocumentTemplateRepository;
use App\Services\Shared\BusinessCalendar;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Regional and Localization settings (Doc 11 section 5) and exchange rate
 * precision (section 7) - Doc 11 Phase B: what a business starts with and
 * its country recommends, how a save reaches every page, new templates and
 * report PDFs, the day weeks start, the fiscal year, rates kept to their
 * precision, and a country change once the business has transacted.
 * Wednesday 28 October 2026.
 */
class RegionalSettingsTest extends TestCase
{
    use MakesSalesDocuments;
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const REGIONAL = [
        'date_format' => 'm/d/Y', 'time_format' => '12h', 'number_format' => 'dot_comma', 'week_start' => 'sunday', 'paper_size' => 'letter', 'fiscal_year_start_month' => 7,
    ];

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Currency::firstOrCreate(['code' => 'EUR'], ['name' => 'Euro', 'symbol' => '€', 'is_default' => false, 'is_active' => true]);

        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();
    }

    public function test_a_business_keeps_writing_as_its_documents_did_and_its_country_recommends(): void
    {
        $this->getJson('/api/v1/business-settings/regional')
            ->assertOk()
            ->assertExactJson(['data' => [
                'date_format' => 'd M Y', 'time_format' => '24h', 'number_format' => 'comma_dot', 'week_start' => 'monday', 'paper_size' => 'a4', 'fiscal_year_start_month' => 1,
                'country'     => null,
                'recommended' => ['date_format' => 'd M Y', 'time_format' => '24h', 'number_format' => 'comma_dot', 'week_start' => 'monday', 'paper_size' => 'a4', 'fiscal_year_start_month' => 1],
            ]]);

        $this->postJson('/api/v1/business-settings', $this->profile())->assertOk();

        $this->getJson('/api/v1/business-settings/regional')
            ->assertJsonPath('data.country', 'BD')
            ->assertJsonPath('data.recommended', ['date_format' => 'd/m/Y', 'time_format' => '12h', 'number_format' => 'lakh', 'week_start' => 'saturday', 'paper_size' => 'a4', 'fiscal_year_start_month' => 7])
            ->assertJsonPath('data.date_format', 'd M Y');
    }

    public function test_saved_settings_reach_every_page_new_templates_and_the_fiscal_year(): void
    {
        $business = $this->businessOf($this->user);

        $this->putJson('/api/v1/business-settings/regional', self::REGIONAL)
            ->assertOk()
            ->assertJsonPath('message', 'Regional settings saved.')
            ->assertJsonPath('data.paper_size', 'letter');

        $this->getJson('/api/v1/navigation')->assertJsonPath('data.workspace.regional', [
            'date_format' => 'm/d/Y', 'time_format' => '12h', 'number_format' => 'dot_comma', 'timezone' => app_display_timezone(), 'rate_precision' => 6,
        ]);

        $invoice = collect($this->getJson('/api/v1/business-settings/templates')->assertOk()->json('data'))->firstWhere('type', 'invoice');
        $this->assertSame(['m/d/Y', 'dot_comma', 'letter'], [$invoice['date_format'], $invoice['number_format'], $invoice['paper_size']]);

        $this->assertSame(7, app(BusinessCalendar::class)->financialYearStart($business->fresh()));
    }

    public function test_templates_already_changed_take_the_formats_only_when_asked(): void
    {
        $business = $this->businessOf($this->user);
        app(DocumentTemplateRepository::class)->save($business, DocumentType::Invoice, Arr::except(DocumentTemplate::defaultsFor(DocumentType::Invoice)->settings(null), 'signature_file_id'));

        $this->putJson('/api/v1/business-settings/regional', self::REGIONAL)->assertOk();
        $this->assertSame(['d M Y', 'a4'], [$business->documentTemplates()->sole()->date_format, $business->documentTemplates()->sole()->paper_size]);

        $this->putJson('/api/v1/business-settings/regional', [...self::REGIONAL, 'apply_to_templates' => true])->assertOk();
        $this->assertSame(['m/d/Y', 'dot_comma', 'letter'], [$business->documentTemplates()->sole()->date_format, $business->documentTemplates()->sole()->number_format, $business->documentTemplates()->sole()->paper_size]);
    }

    public function test_each_choice_comes_from_its_list(): void
    {
        $this->putJson('/api/v1/business-settings/regional', [
            'date_format' => 'Y/d/m', 'time_format' => '25h', 'number_format' => 'roman', 'week_start' => 'friday', 'paper_size' => 'a3', 'fiscal_year_start_month' => 13,
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['date_format', 'time_format', 'number_format', 'week_start', 'paper_size', 'fiscal_year_start_month'])
            ->assertJsonPath('errors.paper_size.0', 'Choose A4 or Letter.');
    }

    public function test_weeks_start_on_the_businesss_own_day(): void
    {
        $this->getJson('/api/v1/dashboard?range=this_week')->assertJsonPath('data.from', '2026-10-26')->assertJsonPath('data.to', '2026-11-01');
        $this->assertSame('Week of 28 Sep 2026', $this->getJson('/api/v1/dashboard?date_from=2026-10-01&date_to=2026-11-09')->json('data.chart.0.label'));

        $this->putJson('/api/v1/business-settings/regional', self::REGIONAL)->assertOk();

        // The week's first day is written the business's way too.
        $this->getJson('/api/v1/dashboard?range=this_week')->assertJsonPath('data.from', '2026-10-25')->assertJsonPath('data.to', '2026-10-31');
        $this->assertSame('Week of 09/27/2026', $this->getJson('/api/v1/dashboard?date_from=2026-10-01&date_to=2026-11-09')->json('data.chart.0.label'));
    }

    public function test_a_report_pdf_is_in_the_regional_formats_and_on_the_regional_paper(): void
    {
        $this->issuedInvoice();
        $this->putJson('/api/v1/business-settings/regional', self::REGIONAL)->assertOk();

        // On the page as well: the report's dates, and its rows' days.
        $this->getJson('/api/v1/reports/outstanding-invoices')->assertOk()->assertJsonPath('data.period', 'As of 10/28/2026');
        $trend = $this->getJson('/api/v1/reports/sales-trend')->assertOk()->assertJsonPath('data.period', '10/01/2026 - 10/31/2026')->json('data.rows');
        $this->assertSame('10/28/2026', $trend[27]['period']);

        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200)]);

        $this->get('/api/v1/reports/outstanding-invoices/pdf', ['Accept' => 'application/json'])->assertOk();

        // Wide, so Letter on its side; dates month first, amounts with a decimal comma.
        Http::assertSent(fn ($request) => collect($request->data())->firstWhere('name', 'paperWidth')['contents'] === '11'
            && str_contains($html = (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'], '10/28/2026')
            && str_contains($html, '335,50'));
    }

    public function test_exchange_rates_keep_the_businesss_precision(): void
    {
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['EUR'], 'rate_precision' => 9])
            ->assertUnprocessable()
            ->assertJsonPath('errors.rate_precision.0', 'Keep exchange rates to between 2 and 8 decimal places.');

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['EUR'], 'rate_precision' => 4])
            ->assertOk()
            ->assertJsonPath('data.rate_precision', 4);

        $this->postJson('/api/v1/exchange-rates', ['currency' => 'EUR', 'rate' => '1.23456789', 'effective_date' => '2026-10-28'])->assertCreated();
        $this->assertSame('1.23460000', ExchangeRate::query()->latest('id')->value('rate'));

        // Left out, the precision stays as it is.
        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'USD', 'additional_currencies' => ['EUR']])->assertJsonPath('data.rate_precision', 4);
    }

    public function test_a_new_country_is_asked_about_once_the_business_has_transacted(): void
    {
        $this->postJson('/api/v1/business-settings', $this->profile())->assertOk();

        // Nothing recorded yet: the country simply changes.
        $this->postJson('/api/v1/business-settings', $this->profile(['country' => 'PK']))->assertOk()->assertJsonPath('data.country', 'PK');

        $this->issuedInvoice();

        $this->postJson('/api/v1/business-settings', $this->profile(['country' => 'IN']))
            ->assertUnprocessable()
            ->assertJsonPath('errors.country_change.0', 'Your business has transactions from when it was in Pakistan. They stay as they are; new documents follow the new country. Change it anyway?');
        $this->assertSame('PK', $this->businessOf($this->user)->businessSetting()->value('country'));

        $this->postJson('/api/v1/business-settings', $this->profile(['country' => 'IN', 'confirm_country_change' => true]))->assertOk()->assertJsonPath('data.country', 'IN');
    }

    public function test_a_new_time_zone_reaches_the_sidebar(): void
    {
        $this->getJson('/api/v1/navigation')->assertJsonPath('data.workspace.regional.timezone', app_display_timezone());

        $this->postJson('/api/v1/business-settings', $this->profile(['timezone' => 'Asia/Dhaka']))->assertOk();

        $this->getJson('/api/v1/navigation')->assertJsonPath('data.workspace.regional.timezone', 'Asia/Dhaka');
    }

    /** A business profile, as Settings > Business sends it. */
    private function profile(array $overrides = []): array
    {
        return [
            'business_name'           => 'Uddin Traders Ltd',
            'language'                => 'en',
            'country'                 => 'BD',
            'timezone'                => 'Asia/Dhaka',
            'stock_accounting_method' => 'fifo',
            ...$overrides,
        ];
    }
}
