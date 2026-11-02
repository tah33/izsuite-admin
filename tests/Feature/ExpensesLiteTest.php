<?php

namespace Tests\Feature;

use App\Jobs\AddExpenseCategories;
use App\Models\Admin\Currency;
use App\Models\User\Contact;
use App\Models\User\Expense;
use App\Models\User\ExpenseCategory;
use App\Models\User\TaxRate;
use App\Models\User\User;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\OffersExpensesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Expenses Lite's expenses (Doc 10, Phase A): a draft added, totalled and
 * changed, paid to a contact or to a name typed in; recorded - numbered and
 * owed to the payee (Pay Later); voided instead of deleted; a receipt recorded
 * twice caught; the list's filters; Settings > Expenses and the categories;
 * the default expense tax; the PDF; and what an expense names kept from being
 * deleted. Most tests start from this expense to City Power:
 *
 *   rent, 1 at 500.00           500.00
 *   utilities, 1 at 100.00      100.00 + VAT 15.00
 *   subtotal 600.00, tax 15.00, total 615.00
 */
class ExpensesLiteTest extends TestCase
{
    use OffersExpensesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Contact $payee;

    private ExpenseCategory $rent;

    private ExpenseCategory $utilities;

    private TaxRate $vat;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->offerExpensesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));

        $business = $this->businessOf($this->user);
        $business->forceFill(['tax_mode' => 'enabled'])->save();

        $this->vat       = $business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT15', 'rate' => '15', 'treatment' => 'standard', 'for_expenses' => true, 'recoverability' => 'recoverable', 'effective_from' => '2026-01-01']);
        $this->payee     = $business->contacts()->create(['contact_type' => 'business', 'role' => 'supplier', 'name' => 'City Power', 'supplier_payment_terms' => 15]);
        $this->rent      = $business->expenseCategories()->create(['name' => 'Rent']);
        $this->utilities = $business->expenseCategories()->create(['name' => 'Utilities', 'tax_rate_id' => $this->vat->id]);
    }

    public function test_a_draft_is_totalled_and_due_on_the_payee_s_terms(): void
    {
        $this->postJson('/api/v1/expenses', $this->expense())
            ->assertCreated()
            ->assertJsonPath('message', 'Draft expense saved.')
            ->assertJsonPath('data.status', 'draft')
            ->assertJsonPath('data.number', null)
            ->assertJsonPath('data.payee.name', 'City Power')
            ->assertJsonPath('data.receipt_number', 'R-1001')
            ->assertJsonPath('data.payment_terms', 15)
            ->assertJsonPath('data.due_date', '2026-11-12')
            ->assertJsonPath('data.subtotal', '600.00')
            ->assertJsonPath('data.grand_total', '615.00')
            ->assertJsonPath('data.taxes.0.tax_amount', '15.00')
            ->assertJsonPath('data.lines.0.category', 'Rent')
            ->assertJsonPath('data.lines.1.category', 'Utilities')
            ->assertJsonPath('data.lines.1.amount', '100.00')
            ->assertJsonPath('data.actions', ['edit', 'record', 'delete']);

        // A payee typed in owes on the business's terms - and is never made a contact.
        $contacts = Contact::count();
        $typed    = $this->postJson('/api/v1/expenses', $this->expense(['contact_id' => null, 'payee_name' => 'Corner Taxi Stand']))
            ->assertCreated()
            ->assertJsonPath('data.payee.name', 'Corner Taxi Stand')
            ->assertJsonPath('data.payee_name', 'Corner Taxi Stand')
            ->assertJsonPath('data.contact_id', null)
            ->assertJsonPath('data.due_date', '2026-11-27')
            ->json('data.id');
        $this->assertSame($contacts, Contact::count());

        // A draft changes freely; its totals are worked out again.
        $this->putJson("/api/v1/expenses/{$typed}", $this->expense(['contact_id' => null, 'payee_name' => 'Corner Taxi Stand', 'lines' => [$this->rentLine('2', '40.00')]]))
            ->assertOk()
            ->assertJsonPath('message', 'Expense saved.')
            ->assertJsonPath('data.grand_total', '80.00');

        $this->postJson('/api/v1/expenses/calculate', ['lines' => [['quantity' => '3', 'unit_price' => '10.00', 'tax_rate_id' => $this->vat->id]]])
            ->assertOk()
            ->assertJsonPath('data.grand_total', '34.50')
            ->assertJsonPath('data.lines.0.amount', '30.00');
    }

    public function test_an_expense_is_checked_before_it_is_kept(): void
    {
        $business = $this->businessOf($this->user);
        $customer = $business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders']);
        $retired  = $business->expenseCategories()->create(['name' => 'Old Costs', 'is_active' => false]);
        $input    = $business->taxRates()->create(['name' => 'Input VAT', 'code' => 'IN5', 'rate' => '5', 'treatment' => 'standard', 'for_purchases' => true, 'effective_from' => '2026-01-01']);

        $this->postJson('/api/v1/expenses', $this->expense(['contact_id' => null]))
            ->assertJsonValidationErrors(['contact_id' => 'Choose the payee, or type their name.']);
        $this->postJson('/api/v1/expenses', $this->expense(['contact_id' => $customer->id]))
            ->assertJsonValidationErrors(['contact_id' => "Uddin Traders is only a customer. Choose a supplier, or type the payee's name."]);
        $this->postJson('/api/v1/expenses', $this->expense(['lines' => [[...$this->rentLine(), 'expense_category_id' => null]]]))
            ->assertJsonValidationErrors(['lines.0.expense_category_id' => 'Choose a category.']);
        $this->postJson('/api/v1/expenses', $this->expense(['lines' => [[...$this->rentLine(), 'expense_category_id' => $retired->id]]]))
            ->assertJsonValidationErrors(['lines.0.expense_category_id' => 'Old Costs is switched off.']);
        $this->postJson('/api/v1/expenses', $this->expense(['lines' => [$this->rentLine('1', '0')]]))
            ->assertJsonValidationErrors(['lines.0.unit_price' => 'The amount must be more than zero.']);
        $this->postJson('/api/v1/expenses', $this->expense(['lines' => [[...$this->rentLine(), 'tax_rate_id' => $input->id]]]))
            ->assertJsonValidationErrors(['lines.0.tax_rate_id' => 'Choose one of your expense taxes.']);
        $this->postJson('/api/v1/expenses', $this->expense(['lines' => []]))->assertJsonValidationErrors(['lines' => 'Add at least one line.']);

        $this->assertSame(0, Expense::count());
    }

    public function test_recording_numbers_a_draft_and_makes_it_owed(): void
    {
        $id = $this->draft();

        $this->postJson("/api/v1/expenses/{$id}/record")
            ->assertOk()
            ->assertJsonPath('message', 'Expense EXP-00001 recorded.')
            ->assertJsonPath('data.number', 'EXP-00001')
            ->assertJsonPath('data.status', 'recorded')
            ->assertJsonPath('data.payment_status', 'unpaid')
            ->assertJsonPath('data.balance_due', '615.00')
            ->assertJsonPath('data.overdue', false)
            ->assertJsonPath('data.actions', ['record_payment', 'void']);

        // What it says about the payee and the categories stays as it was recorded.
        $this->payee->update(['name' => 'City Power Ltd']);
        $this->rent->update(['name' => 'Office Rent']);
        $this->getJson("/api/v1/expenses/{$id}")->assertJsonPath('data.payee.name', 'City Power')->assertJsonPath('data.lines.0.category', 'Rent');

        // A recorded expense is never changed, deleted or recorded again.
        $this->putJson("/api/v1/expenses/{$id}", $this->expense())
            ->assertStatus(409)
            ->assertJsonPath('message', 'A recorded expense cannot be changed. Void it and record it again if it is wrong.');
        $this->deleteJson("/api/v1/expenses/{$id}")->assertStatus(409);
        $this->postJson("/api/v1/expenses/{$id}/record")->assertStatus(409);

        // One paid to a name typed in keeps that name.
        $typed = $this->draft(['contact_id' => null, 'payee_name' => 'Corner Taxi Stand', 'receipt_number' => null]);
        $this->postJson("/api/v1/expenses/{$typed}/record")->assertOk()->assertJsonPath('data.number', 'EXP-00002')->assertJsonPath('data.payee.name', 'Corner Taxi Stand');
    }

    public function test_a_receipt_recorded_before_is_recorded_again_only_when_confirmed(): void
    {
        $first = $this->draft();
        $this->postJson("/api/v1/expenses/{$first}/record")->assertOk();

        // The same payee and receipt number, for another amount: asked once.
        $again = $this->draft(['lines' => [$this->rentLine('1', '200.00')]]);
        $this->postJson("/api/v1/expenses/{$again}/record")
            ->assertUnprocessable()
            ->assertJsonPath('errors.duplicate.0', 'EXP-00001 already has receipt number R-1001 from this payee. Record this one anyway?');
        $this->assertSame('draft', Expense::find($again)->status->value);
        $this->postJson("/api/v1/expenses/{$again}/record", ['confirmed' => true])->assertOk();

        // The same date, amount and currency too: only with a reason, which it keeps.
        $exact = $this->draft();
        $this->postJson("/api/v1/expenses/{$exact}/record", ['confirmed' => true])
            ->assertUnprocessable()
            ->assertJsonPath('errors.duplicate_reason.0', 'EXP-00001 has the same receipt from this payee - number R-1001, its date, amount and currency. Give a reason to record this one too.');
        $this->postJson("/api/v1/expenses/{$exact}/record", ['reason' => 'Billed twice by mistake, paid twice'])
            ->assertOk()
            ->assertJsonPath('data.duplicate_reason', 'Billed twice by mistake, paid twice');

        // Without a receipt number, the same day and amount is asked about - a name typed in matched whatever its case.
        $taxi = $this->draft(['contact_id' => null, 'payee_name' => 'Corner Taxi', 'receipt_number' => null, 'lines' => [$this->rentLine('1', '12.00')]]);
        $this->postJson("/api/v1/expenses/{$taxi}/record")->assertOk();
        $ride = $this->draft(['contact_id' => null, 'payee_name' => 'corner taxi', 'receipt_number' => null, 'lines' => [$this->rentLine('1', '12.00')]]);
        $this->postJson("/api/v1/expenses/{$ride}/record")
            ->assertUnprocessable()
            ->assertJsonPath('errors.duplicate.0', 'EXP-00004 was recorded for this payee on the same day, for the same amount. Record this one anyway?');

        // Different receipt numbers are different receipts, whatever the amount.
        $this->postJson('/api/v1/expenses/'.$this->draft(['receipt_number' => 'R-1002']).'/record')->assertOk();

        // Switched off under Settings > Expenses, nothing is checked.
        $this->putJson('/api/v1/business-settings/expenses', ['payment_terms' => 30, 'notes' => null, 'duplicate_check' => false])->assertOk();
        $this->postJson("/api/v1/expenses/{$ride}/record")->assertOk();
    }

    public function test_a_recorded_expense_is_voided_and_a_draft_deleted(): void
    {
        $id = $this->draft();
        $this->postJson("/api/v1/expenses/{$id}/void")
            ->assertStatus(409)
            ->assertJsonPath('message', 'Only a recorded expense can be voided. A draft is deleted instead.');

        $this->postJson("/api/v1/expenses/{$id}/record")->assertOk();
        $this->postJson("/api/v1/expenses/{$id}/void", ['reason' => 'Entered twice'])
            ->assertOk()
            ->assertJsonPath('message', 'Expense EXP-00001 voided.')
            ->assertJsonPath('data.status', 'void')
            ->assertJsonPath('data.payment_status', 'not_applicable')
            ->assertJsonPath('data.balance_due', '0.00')
            ->assertJsonPath('data.status_reason', 'Entered twice')
            ->assertJsonPath('data.actions', []);

        $draft = $this->draft();
        $this->deleteJson("/api/v1/expenses/{$draft}")->assertOk()->assertJsonPath('message', 'Draft expense deleted.');
        $this->getJson("/api/v1/expenses/{$draft}")->assertNotFound();
    }

    public function test_the_list_is_searched_and_filtered(): void
    {
        $draft   = $this->draft(['receipt_number' => 'R-2002']);
        $overdue = $this->draft(['document_date' => '2026-09-01', 'receipt_number' => 'R-0900', 'lines' => [$this->rentLine()]]);
        $current = $this->draft(['receipt_number' => 'R-1001']);
        $typed   = $this->draft(['contact_id' => null, 'payee_name' => 'Corner Taxi', 'receipt_number' => null, 'lines' => [[...$this->rentLine('1', '12.00'), 'description' => 'Ride to the bank']]]);

        foreach ([$overdue, $current, $typed] as $id) {
            $this->postJson("/api/v1/expenses/{$id}/record")->assertOk();
        }
        $this->postJson("/api/v1/expenses/{$typed}/void")->assertOk();

        $ids = fn (string $query) => collect($this->getJson("/api/v1/expenses{$query}")->assertOk()->json('data'))->pluck('id')->sort()->values()->all();

        $this->assertSame([$draft], $ids('?status=draft'));
        $this->assertSame([$overdue, $current], $ids('?payment_status=outstanding'));
        $this->assertSame([$overdue], $ids('?overdue=1'));
        $this->assertSame([$current], $ids('?search=R-1001'));
        $this->assertSame([$typed], $ids('?search=Corner'));
        $this->assertSame([$typed], $ids('?search=bank'));
        $this->assertSame([$draft, $current], $ids('?search=Utilities'));
        $this->assertSame([$draft, $current], $ids("?category_id={$this->utilities->id}"));
        $this->assertSame([$draft, $overdue, $current], $ids("?contact_id={$this->payee->id}"));
        $this->assertSame([$overdue], $ids('?date_to=2026-09-30'));

        $rows = collect($this->getJson('/api/v1/expenses')->json('data'))->keyBy('id');
        $this->assertSame([['edit', 'record', 'delete'], ['record_payment', 'void'], []], [$rows[$draft]['actions'], $rows[$overdue]['actions'], $rows[$typed]['actions']]);
        $this->assertSame([['Rent', 'Utilities'], ['Rent']], [$rows[$current]['categories'], $rows[$overdue]['categories']]);
        $this->assertSame([true, false], [$rows[$overdue]['overdue'], $rows[$draft]['overdue']]);
        $this->assertSame(['City Power', 'Corner Taxi', '0.00'], [$rows[$current]['payee'], $rows[$typed]['payee'], $rows[$current]['amount_paid']]);

        $csv = $this->get('/api/v1/expenses/export?status=recorded')->assertOk()->streamedContent();
        $this->assertStringContainsString('Date,Number,"Receipt Number",Payee,Categories,Status', $csv);
        $this->assertStringContainsString('2026-09-01,EXP-00001,R-0900,"City Power",Rent,Recorded,Unpaid,Overdue,2026-09-16,USD,500.00,0.00,500.00', $csv);
    }

    public function test_another_business_s_expense_is_not_found(): void
    {
        $id = $this->draft();

        $this->signIn();

        $this->getJson("/api/v1/expenses/{$id}")->assertNotFound();
        $this->postJson("/api/v1/expenses/{$id}/record")->assertNotFound();
        $this->deleteJson("/api/v1/expenses/{$id}")->assertNotFound();
        $this->putJson("/api/v1/expense-categories/{$this->rent->id}", ['name' => 'Mine'])->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/expenses')->assertOk()->json('data'));
    }

    public function test_a_business_without_expenses_has_none(): void
    {
        $this->businessOf($this->user)->workspaceApps()->delete();

        $this->getJson('/api/v1/expenses')->assertForbidden();
        $this->postJson('/api/v1/expenses', $this->expense())->assertForbidden();
        $this->getJson('/api/v1/expense-categories')->assertForbidden();
        $this->getJson('/api/v1/business-settings/expenses')->assertForbidden();
    }

    public function test_settings_say_what_new_expenses_start_with(): void
    {
        $this->getJson('/api/v1/business-settings/expenses')
            ->assertOk()
            ->assertExactJson(['data' => ['payment_terms' => 30, 'notes' => null, 'duplicate_check' => true, 'default_expense_tax_id' => null]]);

        $this->putJson('/api/v1/business-settings/expenses', ['payment_terms' => 7, 'notes' => 'Keep the receipt.', 'duplicate_check' => false])
            ->assertOk()
            ->assertJsonPath('message', 'Expense settings saved successfully.')
            ->assertJsonPath('data.duplicate_check', false);

        // The form starts from them - and from the payee, once a contact is chosen.
        $this->getJson('/api/v1/expenses/defaults')
            ->assertOk()
            ->assertJsonPath('data.payment_terms', 7)
            ->assertJsonPath('data.due_date', '2026-11-04')
            ->assertJsonPath('data.notes', 'Keep the receipt.')
            ->assertJsonPath('data.line_discounts', false);

        $this->getJson("/api/v1/expenses/defaults?contact_id={$this->payee->id}")
            ->assertOk()
            ->assertJsonPath('data.payment_terms', 15)
            ->assertJsonPath('data.payee.id', $this->payee->id);
    }

    public function test_categories_are_one_list_switched_off_once_used(): void
    {
        $this->getJson('/api/v1/expense-categories')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Rent')
            ->assertJsonPath('data.1.tax_rate_id', $this->vat->id)
            ->assertJsonPath('data.1.in_use', false);

        $this->postJson('/api/v1/expense-categories', ['name' => 'Rent'])->assertJsonValidationErrors(['name' => 'You already have a category of that name.']);
        $fuel = $this->postJson('/api/v1/expense-categories', ['name' => 'Fuel', 'description' => 'Vans and the generator'])
            ->assertCreated()
            ->assertJsonPath('message', 'Category "Fuel" added.')
            ->assertJsonPath('data.is_active', true)
            ->json('data.id');

        $this->putJson("/api/v1/expense-categories/{$fuel}", ['name' => 'Fuel and Oil', 'tax_rate_id' => $this->vat->id, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('data.name', 'Fuel and Oil')
            ->assertJsonPath('data.is_active', false);
        $this->deleteJson("/api/v1/expense-categories/{$fuel}")->assertOk()->assertJsonPath('message', 'Category deleted.');

        // One on an expense is switched off, not deleted.
        $this->draft();
        $this->getJson('/api/v1/expense-categories')->assertJsonPath('data.0.in_use', true);
        $this->deleteJson("/api/v1/expense-categories/{$this->rent->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This category is on expenses, so it cannot be deleted. Switch it off instead.');
    }

    public function test_a_new_business_starts_with_the_starter_categories(): void
    {
        $owner = User::factory()->create();
        $this->businessOf($owner);

        AddExpenseCategories::dispatchSync($owner->id);
        AddExpenseCategories::dispatchSync($owner->id);

        $this->assertSame(ExpenseCategory::STARTER, $this->businessOf($owner)->expenseCategories()->orderBy('id')->pluck('name')->all());

        // Added from the Workspace page, a business gets them as it is set up.
        $added = $owner->workspaces()->create(['name' => 'Second Business']);
        app(SetUpNewWorkspace::class)->handle($added, $owner, 'api');
        $this->assertSame(10, $added->expenseCategories()->count());
    }

    public function test_the_default_expense_tax_is_the_business_s_own(): void
    {
        $this->putJson('/api/v1/business-settings/taxes', ['tax_mode' => 'enabled', 'default_expense_tax_id' => $this->vat->id])
            ->assertOk()
            ->assertJsonPath('data.default_expense_tax_id', $this->vat->id);

        $this->getJson('/api/v1/expenses/defaults')->assertJsonPath('data.default_tax_id', $this->vat->id);

        $this->deleteJson("/api/v1/tax-rates/{$this->vat->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', '"VAT" is your default expense tax, so it cannot be deleted. Choose another default - or none - under Tax Options first.');
    }

    public function test_an_expense_record_is_a_pdf(): void
    {
        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);

        $id = $this->draft();
        $this->get("/api/v1/expenses/{$id}/pdf", ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('stamp--draft', $this->sentHtml());

        $this->postJson("/api/v1/expenses/{$id}/record")->assertOk();
        $this->get("/api/v1/expenses/{$id}/pdf?download=1", ['Accept' => 'application/json'])
            ->assertOk()
            ->assertHeader('Content-Type', 'application/pdf')
            ->assertHeader('Content-Disposition', 'attachment; filename=Expense-EXP-00001.pdf');

        $html = $this->sentHtml();
        foreach (['Expense', 'EXP-00001', 'Payee', 'City Power', 'Receipt No.', 'R-1001', 'Utilities', 'Balance Due'] as $text) {
            $this->assertStringContainsString($text, $html);
        }

        // Its template is there to edit and preview, and its numbering.
        $this->assertContains('expense', collect($this->getJson('/api/v1/business-settings/templates')->assertOk()->json('data'))->pluck('type')->all());
        $this->post('/api/v1/business-settings/templates/expense/preview', [
            'color' => '#7c3aed', 'font' => 'sans', 'paper_size' => 'a4', 'header_layout' => 'logo_left', 'show_logo' => true,
            'visible_fields' => ['business_contact', 'customer_contact', 'references', 'location', 'descriptions'],
            'line_columns' => ['quantity', 'unit_price', 'discount', 'tax'], 'show_notes' => true, 'show_terms' => true,
            'show_payment_instructions' => true, 'footer_text' => null, 'language' => 'en', 'date_format' => 'd M Y', 'number_format' => 'comma_dot',
        ], ['Accept' => 'application/json'])->assertOk();
        $this->assertStringContainsString('Electricity bill', $this->sentHtml());
        $this->assertContains('expense', collect($this->getJson('/api/v1/business-settings/numbering')->assertOk()->json('data'))->pluck('type')->all());
    }

    public function test_what_an_expense_names_is_deactivated_not_deleted(): void
    {
        $location = $this->businessOf($this->user)->locations()->create(['user_id' => $this->user->id, 'name' => 'Head Office']);
        $id       = $this->draft(['location_id' => $location->id]);

        $this->getJson("/api/v1/contacts/{$this->payee->id}")->assertJsonPath('data.in_use', true);

        $this->deleteJson("/api/v1/contacts/{$this->payee->id}")->assertStatus(409)->assertJsonPath('message', 'This contact was paid on expenses, so it cannot be deleted. Deactivate it instead.');
        $this->deleteJson("/api/v1/locations/{$location->id}")->assertStatus(409)->assertJsonPath('message', 'Expenses were recorded at this location, so it cannot be deleted. Deactivate it instead.');
        $this->deleteJson("/api/v1/tax-rates/{$this->vat->id}")->assertStatus(409)->assertJsonPath('message', 'This tax is on expenses, so it cannot be deleted. Deactivate it instead.');

        // A tax a category starts with is kept too.
        $spare = $this->businessOf($this->user)->taxRates()->create(['name' => 'Spare', 'code' => 'SP', 'rate' => '5', 'treatment' => 'standard', 'for_expenses' => true, 'effective_from' => '2026-01-01']);
        $this->rent->update(['tax_rate_id' => $spare->id]);
        $this->deleteJson("/api/v1/tax-rates/{$spare->id}")->assertStatus(409)->assertJsonPath('message', '1 expense category starts with this tax. Deactivate it instead, or change those categories first.');

        // Once an expense is recorded, the base currency is its totals' and stays.
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'is_default' => false, 'is_active' => true]);
        $this->postJson("/api/v1/expenses/{$id}/record")->assertOk();

        $this->putJson('/api/v1/business-settings/currencies', ['base_currency' => 'EUR', 'additional_currencies' => []])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('base_currency');
    }

    /** The expense most tests start from, with $fields over it. */
    private function expense(array $fields = []): array
    {
        return [
            'contact_id'     => $this->payee->id,
            'document_date'  => '2026-10-28',
            'currency'       => 'USD',
            'receipt_number' => 'R-1001',
            'lines'          => [
                $this->rentLine(),
                ['expense_category_id' => $this->utilities->id, 'description' => 'Electricity', 'quantity' => '1', 'unit_price' => '100.00', 'tax_rate_id' => $this->vat->id],
            ],
            ...$fields,
        ];
    }

    private function rentLine(string $quantity = '1', string $amount = '500.00'): array
    {
        return ['expense_category_id' => $this->rent->id, 'description' => 'October rent', 'quantity' => $quantity, 'unit_price' => $amount];
    }

    /** A draft expense - its id. */
    private function draft(array $fields = []): int
    {
        return $this->postJson('/api/v1/expenses', $this->expense($fields))->assertCreated()->json('data.id');
    }

    /** The HTML of the last PDF asked of Gotenberg. */
    private function sentHtml(): string
    {
        /** @var Request $request */
        [$request] = Http::recorded()->last();

        return (string) collect($request->data())->firstWhere('filename', 'index.html')['contents'];
    }
}
