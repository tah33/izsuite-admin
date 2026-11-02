<?php

namespace Tests\Feature;

use App\Jobs\AddPaymentTerms;
use App\Models\Shared\ActivityLog;
use App\Models\User\PaymentTerm;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Payment terms (Doc 11 section 14) - Doc 11 Phase D: one named list per
 * business, starting with the suggested terms; contacts, defaults and
 * documents keep days, so a term whose days any of them holds is in use - its
 * days fixed, switched off rather than deleted.
 */
class PaymentTermsApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Workspace $business;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        $this->user     = $this->signIn();
        $this->business = $this->businessOf($this->user);

        app(SetUpNewWorkspace::class)->addPaymentTerms($this->business, $this->user, 'api');
    }

    private function terms(array $headers = []): array
    {
        return collect($this->getJson('/api/v1/payment-terms', $headers)->assertOk()->json('data'))->keyBy('name')->all();
    }

    private function term(string $name): PaymentTerm
    {
        return $this->business->paymentTerms()->where('name', $name)->sole();
    }

    public function test_a_business_starts_with_the_suggested_terms_and_its_defaults_use_some(): void
    {
        $terms = $this->terms();

        $this->assertSame(['Due on Receipt', '7 Days', '15 Days', '30 Days', '45 Days', '60 Days'], array_keys($terms));
        $this->assertSame([0, 7, 15, 30, 45, 60], array_column($terms, 'days'));
        $this->assertSame(['id', 'name', 'days', 'is_active', 'in_use'], array_keys($terms['7 Days']));

        // Sales start Due on Receipt, purchases and expenses on 30 days - before any of them is saved.
        $this->assertSame(['Due on Receipt', '30 Days'], array_keys(array_filter($terms, fn (array $term) => $term['in_use'])));

        // Run again, nothing is added twice.
        app(SetUpNewWorkspace::class)->addPaymentTerms($this->business, $this->user, 'api');

        $this->assertSame(6, $this->business->paymentTerms()->count());
        $this->assertSame(1, ActivityLog::where('description', 'Added the starter payment terms')->count());
    }

    public function test_terms_are_added_renamed_and_switched_off(): void
    {
        $this->postJson('/api/v1/payment-terms', ['name' => '10 Days', 'days' => 10])
            ->assertCreated()
            ->assertJsonPath('message', 'Payment term "10 Days" added.')
            ->assertJsonPath('data.days', 10)
            ->assertJsonPath('data.is_active', true)
            ->assertJsonPath('data.in_use', false);

        $this->postJson('/api/v1/payment-terms', ['name' => '30 Days', 'days' => 31])->assertJsonValidationErrors(['name' => 'You already have a term of that name.']);
        $this->postJson('/api/v1/payment-terms', ['name' => 'Net 30', 'days' => 30])->assertJsonValidationErrors(['days' => 'You already have a term of that many days.']);
        $this->postJson('/api/v1/payment-terms', ['name' => 'Two years', 'days' => 730])->assertJsonValidationErrors(['days' => 'Payment terms are 0 to 365 days.']);
        $this->postJson('/api/v1/payment-terms', ['days' => 20])->assertJsonValidationErrors('name');

        $id = $this->term('45 Days')->id;

        $this->putJson("/api/v1/payment-terms/{$id}", ['name' => 'Net 45', 'days' => 45, 'is_active' => false])
            ->assertOk()
            ->assertJsonPath('message', 'Payment term "Net 45" saved.')
            ->assertJsonPath('data.is_active', false);

        $this->assertFalse($this->terms()['Net 45']['is_active']);
        $this->assertSame('Updated payment term "Net 45"', ActivityLog::where('user_id', $this->user->id)->orderByDesc('id')->value('description'));
    }

    public function test_a_term_in_use_keeps_its_days_and_is_switched_off_rather_than_deleted(): void
    {
        $this->business->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders', 'customer_payment_terms' => 15]);

        $fifteen = $this->term('15 Days');

        $this->assertTrue($this->terms()['15 Days']['in_use']);

        $this->putJson("/api/v1/payment-terms/{$fifteen->id}", ['name' => '15 Days', 'days' => 20])
            ->assertJsonValidationErrors(['days' => 'This term is in use, so its days stay as they are. Add a new term for other days.']);
        $this->putJson("/api/v1/payment-terms/{$fifteen->id}", ['name' => 'Fortnight', 'days' => 15])->assertOk();

        $this->deleteJson("/api/v1/payment-terms/{$fifteen->id}")
            ->assertStatus(409)
            ->assertJsonPath('message', 'This term is in use, so it cannot be deleted. Switch it off instead.');

        // A saved default counts too; one nothing holds goes.
        $this->business->salesSetting()->create(['payment_terms' => 7]);

        $this->assertTrue($this->terms()['7 Days']['in_use']);
        $this->assertFalse($this->terms()['Due on Receipt']['in_use']);

        $this->deleteJson("/api/v1/payment-terms/{$this->term('Due on Receipt')->id}")->assertOk()->assertJsonPath('message', 'Payment term deleted.');
        $this->assertSame(['7 Days', 'Fortnight', '30 Days', '45 Days', '60 Days'], array_keys($this->terms()));
    }

    public function test_another_business_terms_are_not_found(): void
    {
        $other = User::factory()->create();
        $term  = $this->businessOf($other)->paymentTerms()->create(['name' => '30 Days', 'days' => 30]);

        $this->putJson("/api/v1/payment-terms/{$term->id}", ['name' => 'Mine', 'days' => 31])->assertNotFound()->assertJsonPath('message', 'Payment term not found.');
        $this->deleteJson("/api/v1/payment-terms/{$term->id}")->assertNotFound();
    }

    public function test_new_accounts_and_businesses_made_before_the_list_get_the_suggested_terms(): void
    {
        $owner    = User::factory()->create();
        $business = $owner->workspaces()->create(['name' => 'Registered Business']);

        AddPaymentTerms::dispatchSync($owner->id);

        $this->assertSame(array_values(PaymentTerm::STARTER), $business->paymentTerms()->orderBy('days')->pluck('days')->all());

        $migration = require database_path('migrations/2025_01_01_000101_create_payment_terms_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame(6, $this->business->paymentTerms()->count());
        $this->assertSame(6, $business->paymentTerms()->count());
    }
}
