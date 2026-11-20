<?php

namespace Tests\Feature;

use App\Mail\DocumentMail;
use App\Models\Admin\Currency;
use App\Models\Billing\Package;
use App\Models\Frontend\Application;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\DocumentEmail;
use App\Models\User\Item;
use App\Models\User\Location;
use App\Models\User\PaymentAccount;
use App\Models\User\PosOrder;
use App\Models\User\SalesDocument;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceMember;
use App\Support\Permissions;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Doc 18 Phase C4: the receipt (sections 18.19 and 18.20) - a POS sale's PDF
 * on an 80 mm roll, made by Gotenberg (faked here), its first copy its
 * cashier's and every later one a reprint, needing Reprint Receipts and
 * saying so; and e-mailed to the customer as the original.
 *
 * At Front Counter (R1) in Dhaka, with VAT at 15%: two Black Teas at 3.50,
 * one with no sugar, and a Mug at 8.00 - 15.00, VAT 2.25, 17.25 - paid in
 * cash from a 20.00 note. Thursday 19 November 2026.
 *
 * And (Phase D4) the formal invoice: the sale's own invoice given the
 * business's invoice number, once, for a named customer - never a second
 * sale - naming its receipt.
 */
class PosReceiptsTest extends TestCase
{
    use OffersSalesLite;
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private const ORDERS = '/api/v1/pos/orders';

    private const REGISTERS = '/api/v1/pos/registers';

    private User $user;

    private Workspace $business;

    private Location $dhaka;

    private Contact $walkIn;

    private Item $tea;

    private Item $mug;

    private int $cash;

    /** Whether the faked Gotenberg is away. */
    private bool $gotenbergDown = false;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        Currency::firstOrCreate(['code' => 'USD'], ['name' => 'US Dollar', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);

        $this->offerSalesLite();
        Application::create(['app_key' => 'pos', 'name' => 'Point of Sale', 'family_key' => 'pos', 'edition' => 'full', 'category_id' => Application::sole()->category_id, 'sort_order' => 30, 'is_active' => true, 'status' => 'available'])
            ->packages()->attach(Package::pluck('id'));

        $this->travelTo(Carbon::parse('2026-11-19 10:00:00', 'UTC'));
        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);
        $this->business->forceFill(['base_currency' => 'USD', 'tax_mode' => 'enabled'])->save();
        $this->business->taxRates()->create(['name' => 'VAT', 'code' => 'VAT15', 'rate' => '15', 'treatment' => 'standard', 'for_sales' => true, 'effective_from' => '2026-01-01'])
            ->forceFill(['default_for_sales' => true])->save();
        $this->dhaka = $this->business->locations()->create(['user_id' => $this->user->id, 'name' => 'Dhaka', 'code' => 'DHK']);
        $this->business->paymentAccounts()->create(['type' => 'cash', 'name' => 'Shop Till', 'currency' => 'USD']);

        $this->postJson('/api/v1/apps/pos/activate')->assertOk();
        $this->cash = (int) $this->getJson(self::REGISTERS.'/options')->assertOk()->json('data.payment_methods.0.id');

        $this->walkIn = $this->business->contacts()->create(['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Walk-in Customer']);
        $this->business->posSetting()->firstOrFail()->update([
            'walk_in_contact_id' => $this->walkIn->id, 'return_policy' => 'Returns within 14 days with this receipt.', 'receipt_footer' => 'Thank you for shopping with us!',
        ]);

        $this->tea = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Black Tea', 'unit' => 'Piece', 'sale_price' => '3.50']);
        $this->mug = $this->business->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Mug', 'unit' => 'Piece', 'sale_price' => '8.00']);

        $this->openSession($this->addRegister('Front Counter', 'R1', [$this->user->id]));

        Mail::fake();
        Http::preventStrayRequests();
        Http::fake(['*/forms/chromium/convert/html' => fn () => $this->gotenbergDown
            ? Http::response('down', 503)
            : Http::response('%PDF-1.7 fake', 200, ['Content-Type' => 'application/pdf'])]);
    }

    private function addRegister(string $name, string $code, array $cashierIds): array
    {
        return $this->postJson(self::REGISTERS, [
            'name' => $name, 'code' => $code, 'location_id' => $this->dhaka->id, 'currency' => 'USD', 'payment_method_ids' => [$this->cash], 'cashier_ids' => $cashierIds,
        ])->assertCreated()->json('data');
    }

    private function openSession(array $register): int
    {
        return $this->postJson('/api/v1/pos/sessions', ['pos_register_id' => $register['id'], 'opening_float' => '50'])->assertCreated()->json('data.id');
    }

    /** The sale, to $contact - the walk-in customer, unless named - paid in cash from a 20.00 note: its order's id. */
    private function sell(?Contact $contact = null): int
    {
        return (int) $this->postJson(self::ORDERS, [
            'contact_id' => ($contact ?? $this->walkIn)->id,
            'lines'      => [['item_id' => $this->tea->id, 'quantity' => '2', 'note' => 'No sugar'], ['item_id' => $this->mug->id, 'quantity' => '1']],
            'total'      => '17.25',
            'payments'   => [['method_id' => $this->cash, 'amount' => '17.25', 'tendered' => '20.00']],
        ])->assertCreated()->json('data.id');
    }

    private function receipt(int $id): TestResponse
    {
        return $this->get(self::ORDERS."/{$id}/receipt", ['Accept' => 'application/json']);
    }

    private function member(array $permissions): User
    {
        $user = User::factory()->create();
        $role = $this->business->roles()->create(['name' => 'Role '.$user->id, 'permissions' => Permissions::expand($permissions)]);
        $this->business->members()->create(['user_id' => $user->id, 'name' => $user->name, 'status' => 'active', 'role_id' => $role->id, 'location_access' => WorkspaceMember::ALL_LOCATIONS]);

        return $user;
    }

    /** One file of the last PDF asked of Gotenberg - its page, index.html - and the fields it was asked with. */
    private function sent(string $field = 'index.html'): string
    {
        /** @var Request $request */
        [$request] = Http::recorded()->last();
        $data = collect($request->data());

        return (string) ($data->firstWhere('filename', $field) ?? $data->firstWhere('name', $field))['contents'];
    }

    private function prints(int $id): int
    {
        return PosOrder::findOrFail($id)->receipt_prints;
    }

    public function test_the_receipt_shows_the_sale_on_an_80_mm_roll(): void
    {
        $id = $this->sell();

        // The settings change after the sale: its receipt keeps what it ended with.
        $this->business->posSetting()->firstOrFail()->update(['return_policy' => 'No returns.']);

        $response = $this->receipt($id)->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertSame('%PDF-1.7 fake', $response->getContent());
        $this->assertSame('inline; filename=Receipt-R1-00001.pdf', $response->headers->get('Content-Disposition'));
        $this->assertSame(['3.15', 'true'], [$this->sent('paperWidth'), $this->sent('singlePage')]);

        $html = $this->sent();
        foreach ([
            e($this->business->name), 'Dhaka', 'R1-00001', '19 Nov 2026', 'Front Counter (R1)', e($this->user->name),
            'Black Tea', '2 Piece × $3.50', '$7.00', 'No sugar', 'Mug', '1 Piece × $8.00', '$8.00',
            'Subtotal', '$15.00', 'VAT 15%', '$2.25', 'Total', '$17.25', 'Cash', 'Tendered $20.00', 'Change', '$2.75',
            'Returns within 14 days with this receipt.', 'Thank you for shopping with us!', 'Powered by izSuite',
        ] as $shown) {
            $this->assertStringContainsString($shown, $html);
        }

        // The walk-in customer is no one to name, and the first copy is no reprint.
        $this->assertStringNotContainsString('Walk-in Customer', $html);
        $this->assertStringNotContainsString('REPRINT', $html);
        $this->assertStringNotContainsString('No returns.', $html);
        $this->assertSame(1, $this->prints($id));
        $this->assertSame(0, ActivityLog::where('description', 'like', 'Reprinted%')->count());

        // A named customer is.
        $rahman = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders']);
        $this->receipt($this->sell($rahman))->assertOk();
        $this->assertStringContainsString('Rahman Traders', $this->sent());

        // ?download=1 saves it.
        $this->get(self::ORDERS."/{$id}/receipt?download=1")->assertOk()->assertHeader('Content-Disposition', 'attachment; filename=Receipt-R1-00001.pdf');
    }

    public function test_a_copy_after_the_first_is_a_reprint_needing_reprint_receipts(): void
    {
        // A cashier who may sell, at their own register: the first copy is theirs, a second is not.
        $cashier = $this->member(['pos.selling.sell']);
        $mine    = $this->sell();
        Sanctum::actingAs($cashier);
        $this->openSession($this->withOwner(fn () => $this->addRegister('Back Counter', 'R2', [$cashier->id])));
        $theirs = $this->sell();

        $this->receipt($mine)->assertForbidden()->assertJsonPath('message', "Receipt R1-00001 is its cashier's: anyone else needs Reprint Receipts.");
        $this->receipt($theirs)->assertOk();
        $this->assertStringNotContainsString('REPRINT', $this->sent());
        $this->receipt($theirs)->assertForbidden()->assertJsonPath('message', 'Receipt R2-00001 was printed already: a copy needs Reprint Receipts.');
        $this->assertSame([0, 1], [$this->prints($mine), $this->prints($theirs)]);

        // Whoever may reprint makes a copy - marked, counted and logged; one that could not be made is not counted.
        Sanctum::actingAs($this->member(['pos.receipts.reprint']));
        $this->receipt($theirs)->assertOk();
        $this->assertStringContainsString('REPRINT', $this->sent());
        $this->gotenbergDown = true;
        $this->receipt($theirs)->assertStatus(503);
        $this->gotenbergDown = false;
        $this->assertSame(2, $this->prints($theirs));
        $this->assertSame(['Reprinted receipt R2-00001, copy 2'], ActivityLog::where('description', 'like', 'Reprinted%')->pluck('description')->all());

        // Neither selling nor reprinting: no receipt at all. And no sale that is not the business's.
        Sanctum::actingAs($this->member(['pos.selling.open']));
        $this->receipt($theirs)->assertForbidden();
        Sanctum::actingAs($this->user);
        $this->receipt(99999)->assertNotFound()->assertJsonPath('message', 'There is no such sale.');
    }

    public function test_the_receipt_is_emailed_to_the_customer_as_the_original(): void
    {
        $id = $this->sell();
        $this->receipt($id)->assertOk();

        $this->postJson(self::ORDERS."/{$id}/receipt/email", ['to' => 'buyer@example.com'])
            ->assertOk()
            ->assertJsonPath('message', 'Receipt R1-00001 is on its way to buyer@example.com.')
            ->assertJsonPath('data', ['id' => $id, 'number' => 'R1-00001', 'prints' => 1, 'contact_id' => $this->walkIn->id, 'invoice' => null]);

        // Sent from the queue with its PDF - the original, uncounted - greeting the walk-in customer as a customer.
        $email = DocumentEmail::sole();
        $this->assertSame([PosOrder::class, $id, 'buyer@example.com', DocumentEmail::SENT], [$email->emailable_type, $email->emailable_id, $email->to, $email->status]);
        Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => $mail->hasTo('buyer@example.com')
            && $mail->hasSubject("Receipt R1-00001 from {$this->business->name}")
            && str_contains($mail->body, 'Dear Customer,') && str_contains($mail->body, '$17.25')
            && [$mail->filename, $mail->pdf] === ['Receipt-R1-00001.pdf', '%PDF-1.7 fake']);
        $this->assertStringNotContainsString('REPRINT', $this->sent());
        $this->assertSame(1, $this->prints($id));
        $this->assertSame(1, ActivityLog::where('description', 'Emailed receipt R1-00001 to buyer@example.com')->count());

        // A named customer is greeted by name; an address is needed.
        $rahman = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders', 'email' => 'accounts@rahman.test']);
        $this->postJson(self::ORDERS.'/'.$this->sell($rahman).'/receipt/email', ['to' => 'accounts@rahman.test'])->assertOk();
        Mail::assertSent(DocumentMail::class, fn (DocumentMail $mail) => str_contains($mail->body, 'Dear Rahman Traders,'));
        $this->postJson(self::ORDERS."/{$id}/receipt/email", ['to' => 'not an address'])->assertUnprocessable()->assertJsonValidationErrors('to');

        // Someone else's sale is its cashier's to send, unless one may reprint.
        Sanctum::actingAs($this->member(['pos.selling.sell']));
        $this->postJson(self::ORDERS."/{$id}/receipt/email", ['to' => 'buyer@example.com'])->assertForbidden();
    }

    public function test_the_selling_screen_names_the_last_sale_and_a_sale_its_customers_address(): void
    {
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.last_order', null);

        $rahman = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders', 'email' => 'accounts@rahman.test']);
        $this->postJson(self::ORDERS, [
            'contact_id' => $rahman->id, 'lines' => [['item_id' => $this->mug->id, 'quantity' => '1']], 'total' => '9.20',
            'payments'   => [['method_id' => $this->cash, 'amount' => '9.20']],
        ])->assertCreated()->assertJsonPath('data.email', 'accounts@rahman.test');

        $id = $this->sell();
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.last_order', ['id' => $id, 'number' => 'R1-00002', 'prints' => 0, 'contact_id' => $this->walkIn->id, 'invoice' => null]);
        $this->receipt($id)->assertOk();
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.last_order.prints', 1);
    }

    public function test_a_named_customers_sale_is_given_its_formal_invoice_once(): void
    {
        $rahman = $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Rahman Traders']);
        $id     = $this->sell($rahman);

        $this->postJson(self::ORDERS."/{$id}/formal-invoice")->assertOk()
            ->assertJsonPath('message', 'R1-00001 is now invoice INV-00001.')
            ->assertJsonPath('data', ['id' => $id, 'receipt' => 'R1-00001', 'invoice' => 'INV-00001']);

        // The same sale: its invoice renumbered, its order keeping the receipt number - and no second invoice.
        $this->assertSame([['INV-00001']], SalesDocument::all()->map(fn (SalesDocument $document) => [$document->number])->all());
        $this->assertSame('R1-00001', PosOrder::findOrFail($id)->number);
        $this->assertSame(1, ActivityLog::where('description', 'Gave the POS sale R1-00001 its formal invoice number INV-00001')->count());
        $this->postJson(self::ORDERS."/{$id}/formal-invoice")->assertConflict()->assertJsonPath('message', 'R1-00001 has its formal invoice already: INV-00001.');

        // Its PDF is the business's invoice, naming the receipt; the receipt still says R1-00001.
        $this->get(self::ORDERS."/{$id}/formal-invoice", ['Accept' => 'application/json'])->assertOk()->assertHeader('Content-Type', 'application/pdf');
        $this->assertStringContainsString('INV-00001', $this->sent());
        $this->assertStringContainsString('POS Receipt', $this->sent());
        $this->assertSame('8.27', $this->sent('paperWidth'));
        $this->receipt($id)->assertOk();
        $this->assertStringContainsString('R1-00001', $this->sent());
        $this->getJson('/api/v1/pos/sell')->assertOk()->assertJsonPath('data.last_order.invoice', 'INV-00001')->assertJsonPath('data.last_order.contact_id', $rahman->id);

        // The walk-in customer's sale has none.
        $walkIn = $this->sell();
        $this->postJson(self::ORDERS."/{$walkIn}/formal-invoice")->assertConflict()
            ->assertJsonPath('message', 'A formal invoice is made out to a named customer: R1-00002 was sold to the walk-in customer.');
        $this->get(self::ORDERS."/{$walkIn}/formal-invoice", ['Accept' => 'application/json'])->assertConflict()->assertJsonPath('message', 'R1-00002 has no formal invoice yet.');
    }

    /** $make done as the business's owner, then back to whoever was signed in. */
    private function withOwner(callable $make): mixed
    {
        $current = auth()->user();
        Sanctum::actingAs($this->user);
        $made = $make();
        Sanctum::actingAs($current);

        return $made;
    }
}
