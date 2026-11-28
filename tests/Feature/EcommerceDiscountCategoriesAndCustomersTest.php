<?php

namespace Tests\Feature;

use App\Models\User\Contact;
use App\Models\User\EcommerceOrder;
use App\Models\User\StorefrontCustomer;
use App\Models\User\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\ChecksOutAStore;
use Tests\Concerns\OffersSalesLite;
use Tests\Concerns\RunsAStorefront;
use Tests\Concerns\SignsInStoreCustomers;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Who and what a discount is for (Doc 19 section 19.31 - Phase H2b): chosen
 * categories as well as products - a product in one by its listing's item -
 * and everyone, the customers signed in to their account, or chosen contacts
 * signed in to theirs, its e-mail verified; codes and automatic discounts
 * alike, a code not for the customer saying why. A contact merged into
 * another takes its discounts with it.
 */
class EcommerceDiscountCategoriesAndCustomersTest extends TestCase
{
    use ChecksOutAStore;
    use OffersSalesLite;
    use RefreshDatabase;
    use RunsAStorefront;
    use SignsInStoreCustomers;
    use SignsIntoBusinesses;

    private const DISCOUNTS = '/api/v1/ecommerce/discounts';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        $this->travelTo('2026-11-28 10:00:00');
        // The mug is in Kitchen; the towel in no category.
        $this->openStorefront(['Ceramic Mug' => ['sale_price' => '8.00', 'in_kitchen' => true], 'Tea Towel' => ['sale_price' => '3.00']]);
        $this->listed('Ceramic Mug');
        $this->listed('Tea Towel');
        $this->setUpCheckout();
    }

    /** A discount - 10% off the whole order, for everyone, automatic unless $fields give a code - its id, made by the business. */
    private function discount(array $fields): int
    {
        Sanctum::actingAs($this->user);

        return $this->postJson(self::DISCOUNTS, ['kind' => 'percentage', 'value' => 10, 'applies_to' => 'order', 'automatic' => ! isset($fields['code']), ...$fields])->assertCreated()->json('data.id');
    }

    /** $products - two mugs ($16) and a towel ($3) - shipped by Standard ($5), quoted with $code: signed in by $customer's token, or a guest. */
    private function quoted(?string $code = null, ?string $customer = null, array $products = ['ceramic-mug' => 2, 'tea-towel' => 1])
    {
        $this->asCustomer($customer);

        return $this->quote($this->cartWith($products), [
            'address' => ['country' => 'BD'], 'billing_country' => 'BD', 'delivery' => 'shipping', 'shipping_method_id' => $this->ids['Standard'],
            'payment_method_id' => $this->ids['Bank Transfer'], 'discount_code' => $code, 'email' => 'karim@example.test',
        ])->assertOk();
    }

    private function customer(string $name, string $email, string $role = Contact::ROLE_CUSTOMER): Contact
    {
        return $this->business->contacts()->create(['contact_type' => Contact::TYPE_INDIVIDUAL, 'role' => $role, 'name' => $name, 'email' => $email]);
    }

    public function test_a_discount_for_chosen_categories_takes_off_only_their_products(): void
    {
        $kitchen = $this->kitchen->id;
        $this->discount(['name' => 'Kitchen Week', 'applies_to' => 'categories', 'category_ids' => [$kitchen]]);

        // 10% of the mugs alone: $1.60 of $24.00.
        $this->quoted()->assertJsonPath('data.discounts', [['name' => 'Kitchen Week', 'code' => null, 'off' => '1.60']])->assertJsonPath('data.totals.grand_total', '22.40');
        $this->quoted(products: ['tea-towel' => 2])->assertJsonPath('data.discounts', []);

        // A code for Kitchen, taking off more at the same priority, is taken - and refused for a cart without a Kitchen product.
        $this->discount(['name' => 'Kitchen Quarter', 'code' => 'KITCHEN25', 'value' => 25, 'applies_to' => 'categories', 'category_ids' => [$kitchen]]);
        $this->quoted('KITCHEN25')->assertJsonPath('data.discounts', [['name' => 'Kitchen Quarter', 'code' => 'KITCHEN25', 'off' => '4.00']])->assertJsonPath('data.totals.grand_total', '20.00');
        $this->quoted('KITCHEN25', products: ['tea-towel' => 2])->assertJsonPath('data.discount.problem', 'This code is for products not in your cart.');

        $this->getJson(self::DISCOUNTS)->assertJsonPath('data.0.applies_to', 'categories')->assertJsonPath('data.0.categories', [['id' => $kitchen, 'name' => 'Kitchen']]);

        // A product is in a category by its item, as it is now.
        $this->items['Ceramic Mug']->update(['category_id' => null]);
        $this->quoted()->assertJsonPath('data.discounts', []);
    }

    public function test_a_discount_for_signed_in_customers_is_theirs_alone(): void
    {
        $this->discount(['name' => 'Members', 'code' => 'MEMBERS', 'customers' => 'signed_in', 'combines' => true]);
        $this->discount(['name' => 'Welcome Back', 'kind' => 'amount', 'value' => 2, 'customers' => 'signed_in', 'combines' => true]);

        // A guest: the code refused saying why, the automatic one left out without a word.
        $this->quoted('MEMBERS')
            ->assertJsonPath('data.discount.problem', 'Sign in to your account to use this code.')
            ->assertJsonPath('data.discounts', [])
            ->assertJsonPath('data.totals.grand_total', '24.00');

        // Signed in: both, $2.00 and 10% - $3.90 off.
        $karim = $this->verifiedCustomer();
        $this->quoted('MEMBERS', $karim)
            ->assertJsonPath('data.discount.problem', null)
            ->assertJsonPath('data.discounts', [['name' => 'Welcome Back', 'code' => null, 'off' => '2.00'], ['name' => 'Members', 'code' => 'MEMBERS', 'off' => '1.90']])
            ->assertJsonPath('data.totals.grand_total', '20.10');

        $this->asCustomer($karim);
        $this->place($this->cartWith(['ceramic-mug' => 2, 'tea-towel' => 1]), $this->order('20.10', ['discount_code' => 'MEMBERS']))->assertCreated()
            ->assertJsonPath('data.discounts.*.name', ['Welcome Back', 'Members']);

        // A guest placing it with the code is refused under it.
        $this->asCustomer(null);
        $this->place($this->cartWith(['ceramic-mug' => 2, 'tea-towel' => 1]), $this->order('24.00', ['discount_code' => 'MEMBERS']))
            ->assertUnprocessable()->assertJsonPath('errors.discount_code.0', 'Sign in to your account to use this code.');
    }

    public function test_a_discount_for_chosen_contacts_fits_them_signed_in_to_their_verified_account(): void
    {
        $karim = $this->verifiedCustomer();
        $nadia = $this->verifiedCustomer('nadia@example.test', 'Nadia Islam');
        $this->discount(['name' => 'VIP', 'code' => 'VIP5', 'kind' => 'amount', 'value' => 5, 'customers' => 'contacts', 'contact_ids' => [StorefrontCustomer::where('email', 'karim@example.test')->value('contact_id')]]);

        $this->quoted('VIP5')->assertJsonPath('data.discount.problem', 'Sign in to your account to use this code.');
        $this->quoted('VIP5', $nadia)->assertJsonPath('data.discount.problem', 'This code is not for your account.');
        $this->quoted('VIP5', $karim)->assertJsonPath('data.discount.problem', null)->assertJsonPath('data.totals.grand_total', '19.00');

        // An account signed in before its e-mail is verified, where the store allows it, has no contact yet.
        Sanctum::actingAs($this->user);
        $this->putJson('/api/v1/business-settings/ecommerce', ['guest_checkout' => true, 'verify_customer_email' => false, 'price_display' => 'without_tax', 'order_prefix' => 'EC-'])->assertOk();
        $rafi = $this->signUpCustomer('rafi@example.test', 'Rafi Ahmed')->json('data.token');
        $this->assertNotNull($rafi);
        $this->quoted('VIP5', $rafi)->assertJsonPath('data.discount.problem', "Verify your account's e-mail to use this code.");

        $this->asCustomer($karim);
        $this->place($this->cartWith(['ceramic-mug' => 2, 'tea-towel' => 1]), $this->order('19.00', ['discount_code' => 'VIP5']))->assertCreated()
            ->assertJsonPath('data.discounts', [['name' => 'VIP', 'code' => 'VIP5', 'off' => '5.00']]);
        $this->assertSame('karim@example.test', EcommerceOrder::sole()->customer_email);
    }

    public function test_the_discounts_page_keeps_whom_and_what_a_discount_is_for(): void
    {
        $karim    = $this->customer('Karim Rahman', 'karim@example.test');
        $supplier = $this->customer('Uddin Traders', 'uddin@example.test', Contact::ROLE_SUPPLIER);
        $kitchen  = $this->kitchen->id;
        $fields   = ['name' => 'VIP Kitchen', 'code' => 'VIPK', 'kind' => 'percentage', 'value' => 15, 'applies_to' => 'categories', 'category_ids' => [$kitchen], 'customers' => 'contacts', 'contact_ids' => [$karim->id]];

        $id = $this->postJson(self::DISCOUNTS, $fields)->assertCreated()
            ->assertJsonPath('data.applies_to', 'categories')
            ->assertJsonPath('data.categories', [['id' => $kitchen, 'name' => 'Kitchen']])
            ->assertJsonPath('data.customers', 'contacts')
            ->assertJsonPath('data.contacts', [['id' => $karim->id, 'name' => 'Karim Rahman']])
            ->json('data.id');

        // Refused under their fields: none chosen, another business's category, a supplier.
        $elsewhere = $this->businessOf(User::factory()->create())->itemCategories()->create(['name' => 'Elsewhere']);
        $this->postJson(self::DISCOUNTS, [...$fields, 'code' => 'X1', 'category_ids' => []])->assertUnprocessable()->assertJsonPath('errors.category_ids.0', 'Choose the categories it is for.');
        $this->postJson(self::DISCOUNTS, [...$fields, 'code' => 'X2', 'contact_ids' => []])->assertUnprocessable()->assertJsonPath('errors.contact_ids.0', 'Choose the customers it is for.');
        $this->postJson(self::DISCOUNTS, [...$fields, 'code' => 'X3', 'category_ids' => [$elsewhere->id]])->assertUnprocessable()->assertJsonPath('errors.category_ids.0', 'Choose categories of your own.');
        $this->postJson(self::DISCOUNTS, [...$fields, 'code' => 'X4', 'contact_ids' => [$supplier->id]])->assertUnprocessable()->assertJsonPath('errors.contact_ids.0', 'Choose customers of your own.');
        $this->postJson(self::DISCOUNTS, [...$fields, 'code' => 'X5', 'customers' => 'members'])->assertUnprocessable()->assertJsonValidationErrors('customers');

        // For the whole order and everyone again: what it was for goes.
        $this->putJson(self::DISCOUNTS."/{$id}", [...$fields, 'applies_to' => 'order', 'customers' => 'everyone'])->assertOk()
            ->assertJsonPath('data.categories', [])
            ->assertJsonPath('data.contacts', []);

        // What its form finds: the business's categories by name, its active customers by name or e-mail - never a supplier.
        $this->getJson(self::DISCOUNTS.'/categories?search=kit')->assertOk()->assertExactJson(['data' => [['id' => $kitchen, 'name' => 'Kitchen']]]);
        $this->getJson(self::DISCOUNTS.'/contacts?search=karim@')->assertOk()->assertExactJson(['data' => [['id' => $karim->id, 'name' => 'Karim Rahman', 'email' => 'karim@example.test']]]);
        $this->getJson(self::DISCOUNTS.'/contacts?search=uddin')->assertOk()->assertJsonPath('data', []);
        $karim->update(['is_active' => false]);
        $this->getJson(self::DISCOUNTS.'/contacts?search=karim')->assertOk()->assertJsonPath('data', []);
    }

    public function test_a_merged_contact_takes_its_discounts_with_it(): void
    {
        // Karim's account made a contact as it was verified; another of him is kept in the merge.
        $karim = $this->verifiedCustomer();
        $own   = Contact::where('email', 'karim@example.test')->sole();
        $kept  = $this->customer('Karim R.', 'karim.r@example.test');
        $this->discount(['name' => 'VIP', 'code' => 'VIP1', 'kind' => 'amount', 'value' => 1, 'customers' => 'contacts', 'contact_ids' => [$own->id]]);
        $this->discount(['name' => 'Both', 'code' => 'BOTH2', 'kind' => 'amount', 'value' => 2, 'customers' => 'contacts', 'contact_ids' => [$own->id, $kept->id]]);

        $this->getJson("/api/v1/contacts/{$kept->id}/merge?merged_id={$own->id}")->assertOk()->assertJsonPath('data.moving.discounts', 1);
        $this->postJson("/api/v1/contacts/{$kept->id}/merge", ['merged_id' => $own->id])->assertOk();

        // Each names the contact kept, once.
        $this->getJson(self::DISCOUNTS)
            ->assertJsonPath('data.0.contacts', [['id' => $kept->id, 'name' => 'Karim R.']])
            ->assertJsonPath('data.1.contacts', [['id' => $kept->id, 'name' => 'Karim R.']]);

        // His account, linked to the contact merged away, is the kept one's: the code is still his.
        $this->quoted('VIP1', $karim)->assertJsonPath('data.discount.problem', null)->assertJsonPath('data.totals.grand_total', '23.00');
    }
}
