<?php

namespace Tests\Feature;

use App\Models\User\Contact;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\User;
use App\Repositories\Shared\RowLockRepository;
use Database\Seeders\RoleSeeder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\MakesSalesDocuments;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Safe saving (Doc 14 section 22) - Doc 14 Phase A: a create sent again
 * with its key makes one record; a save over someone else's change is
 * refused; a payment checks what is still due with its invoice locked, so
 * one made meanwhile counts.
 */
class SafeSavingTest extends TestCase
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
        $this->offerSalesLite();
        $this->user = $this->signIn();
        $this->travelTo(Carbon::parse('2026-10-28 10:00:00', 'UTC'));
        $this->setUpSales();
    }

    public function test_a_create_sent_again_with_its_key_makes_one_record(): void
    {
        $contact = ['contact_type' => 'individual', 'role' => 'supplier', 'name' => 'Karim Ahmed'];
        $first   = $this->postJson('/api/v1/contacts', $contact, ['Idempotency-Key' => 'contact-key-0001'])->assertCreated();
        $again   = $this->postJson('/api/v1/contacts', $contact, ['Idempotency-Key' => 'contact-key-0001'])->assertCreated();

        $this->assertSame($first->json('data.id'), $again->json('data.id'));
        $this->assertSame('true', $again->headers->get('Idempotent-Replayed'));
        $this->assertSame(1, Contact::where('name', 'Karim Ahmed')->count());

        // The same key with something else: refused. Without a key: a record each time.
        $this->postJson('/api/v1/contacts', [...$contact, 'name' => 'Someone Else'], ['Idempotency-Key' => 'contact-key-0001'])->assertUnprocessable();
        $this->postJson('/api/v1/contacts', ['contact_type' => 'individual', 'role' => 'supplier', 'name' => 'Nadia Islam'])->assertCreated();
        $this->postJson('/api/v1/contacts', ['contact_type' => 'individual', 'role' => 'supplier', 'name' => 'Nadia Islam', 'allow_duplicates' => true])->assertCreated();

        // A refused request gives its key back: corrected, it is sent with it again.
        $this->postJson('/api/v1/contacts', ['contact_type' => 'individual'], ['Idempotency-Key' => 'contact-key-0002'])->assertUnprocessable();
        $this->postJson('/api/v1/contacts', ['contact_type' => 'individual', 'role' => 'customer', 'name' => 'Salma Begum'], ['Idempotency-Key' => 'contact-key-0002'])->assertCreated();
        $this->postJson('/api/v1/contacts', $contact, ['Idempotency-Key' => 'bad key!'])->assertStatus(400);
    }

    public function test_a_payment_sent_again_with_its_key_is_recorded_once(): void
    {
        $id   = $this->issuedInvoice();
        $cash = $this->account();

        $this->withHeader('Idempotency-Key', 'payment-key-0001');
        $this->pay($id, $cash, '100')->assertCreated()->assertJsonPath('data.number', 'RCT-00001');
        $this->pay($id, $cash, '100')->assertCreated()->assertJsonPath('data.number', 'RCT-00001');

        $this->assertSame(1, SalesPayment::count());
        $this->assertSame('235.50', (string) SalesDocument::findOrFail($id)->balance_due);
    }

    public function test_a_save_over_someone_elses_change_is_refused(): void
    {
        $id      = $this->postJson('/api/v1/contacts', ['contact_type' => 'individual', 'role' => 'supplier', 'name' => 'Karim Ahmed'])->assertCreated()->json('data.id');
        $opened  = $this->getJson("/api/v1/contacts/{$id}")->assertOk()->json('data.version');

        $this->assertSame('2026-10-28T10:00:00Z', $opened);

        // Someone else saves a minute later; the first save, from the version opened, is refused.
        $this->travelTo(Carbon::parse('2026-10-28 10:01:00', 'UTC'));
        $saved   = $this->putJson("/api/v1/contacts/{$id}", ['contact_type' => 'individual', 'role' => 'supplier', 'name' => 'Karim Ahmed & Sons'], ['If-Match' => $opened])->assertOk()->json('data.version');

        $this->assertSame('2026-10-28T10:01:00Z', $saved);
        $this->putJson("/api/v1/contacts/{$id}", ['contact_type' => 'individual', 'role' => 'supplier', 'name' => 'Karim Traders'], ['If-Match' => $opened])
            ->assertConflict()
            ->assertJsonPath('message', 'Someone else changed this contact after you opened it. Reload it to see their changes, then make yours again.');

        // From the latest version, or without one, it saves.
        $this->putJson("/api/v1/contacts/{$id}", ['contact_type' => 'individual', 'role' => 'supplier', 'name' => 'Karim Traders'], ['If-Match' => $saved])->assertOk();
        $this->putJson("/api/v1/contacts/{$id}", ['contact_type' => 'individual', 'role' => 'supplier', 'name' => 'Karim Traders Ltd'])->assertOk();

        // A draft invoice the same way.
        $draft   = $this->draft('invoices');
        $version = $this->getJson("/api/v1/sales/invoices/{$draft}")->json('data.version');

        $this->travelTo(Carbon::parse('2026-10-28 10:02:00', 'UTC'));
        $this->putJson("/api/v1/sales/invoices/{$draft}", $this->invoice(['notes' => 'First change']), ['If-Match' => $version])->assertOk();
        $this->putJson("/api/v1/sales/invoices/{$draft}", $this->invoice(['notes' => 'Second change']), ['If-Match' => $version])
            ->assertConflict()->assertJsonPath('message', 'Someone else changed this document after you opened it. Reload it to see their changes, then make yours again.');
    }

    public function test_a_payment_checks_what_is_due_with_the_invoice_locked(): void
    {
        $id   = $this->issuedInvoice();
        $cash = $this->account();

        // Read again under the lock, the invoice has been paid in full meanwhile - by another payment at the same moment.
        $this->app->instance(RowLockRepository::class, new class extends RowLockRepository
        {
            public function lock(Model $model): Model
            {
                $fresh = parent::lock($model);

                if ($fresh instanceof SalesDocument) {
                    $fresh->balance_due = '0.00';
                }

                return $fresh;
            }
        });

        $this->pay($id, $cash, '100')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['amount' => 'That comes to more than the USD 0.00 still due. Overpayments are not taken in Sales Lite.']);

        $this->assertSame(0, SalesPayment::count());
    }
}
