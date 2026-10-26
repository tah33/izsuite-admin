<?php

namespace Tests\Feature;

use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * The migration that gave contacts Doc 03's shape: contacts saved before it -
 * a customer/vendor pair, a company, one address with a typed country - must
 * come through with nothing lost.
 */
class ContactsDocThreeMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2025_01_01_000073_reshape_contacts_for_doc_03.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** A contact as it was saved before the migration. */
    private function legacyContact(Workspace $business, array $fields): int
    {
        return DB::table('contacts')->insertGetId([
            'workspace_id' => $business->id,
            'is_customer'  => true,
            'is_vendor'    => false,
            'name'         => 'Legacy',
            'is_active'    => true,
            'created_at'   => now(),
            'updated_at'   => now(),
            ...$fields,
        ]);
    }

    public function test_existing_contacts_keep_everything_in_their_new_shape(): void
    {
        $user   = User::factory()->create();
        $first  = $user->workspaces()->create(['name' => 'First']);
        $second = $user->workspaces()->create(['name' => 'Second']);

        $migration = require database_path(self::MIGRATION);
        $migration->down();

        $customer = $this->legacyContact($first, ['name' => 'Rahim Uddin', 'company_name' => 'Uddin Traders', 'address' => 'House 12', 'city' => 'Dhaka', 'state' => 'Dhaka', 'postal_code' => '1216', 'country' => 'Bangladesh']);
        $both     = $this->legacyContact($first, ['name' => 'Bose', 'is_vendor' => true, 'country' => 'Atlantis']);
        $supplier = $this->legacyContact($second, ['name' => 'Supplier', 'is_customer' => false, 'is_vendor' => true, 'country' => 'usa']);

        $migration->up();

        $row = fn (int $id) => DB::table('contacts')->find($id);

        $this->assertSame(
            ['C-00001', 'business', 'customer', 'Uddin Traders', 'House 12', 'Dhaka', 'Dhaka', '1216', 'BD', null],
            array_values((array) DB::table('contacts')->where('id', $customer)->first(['contact_number', 'contact_type', 'role', 'trading_name', 'billing_line1', 'billing_city', 'billing_state', 'billing_postal_code', 'billing_country', 'billing_line2'])),
        );

        // A country the list does not know is kept, as the address's second line.
        $this->assertSame(['C-00002', 'both', null, 'Atlantis'], [$row($both)->contact_number, $row($both)->role, $row($both)->billing_country, $row($both)->billing_line2]);

        // Numbered within its own business.
        $this->assertSame(['C-00001', 'supplier', 'US'], [$row($supplier)->contact_number, $row($supplier)->role, $row($supplier)->billing_country]);

        // Each business's counter carries on from its last.
        $this->assertSame(2, DB::table('workspace_sequences')->where(['workspace_id' => $first->id, 'name' => 'contact'])->value('last_value'));
        $this->assertSame('C-00003', $first->contacts()->create(['name' => 'Next'])->contact_number);

        foreach (['is_customer', 'is_vendor', 'company_name', 'country'] as $gone) {
            $this->assertFalse(Schema::hasColumn('contacts', $gone), $gone);
        }
    }

    public function test_rolling_back_gives_contacts_their_old_shape(): void
    {
        $user     = User::factory()->create();
        $business = $user->workspaces()->create(['name' => 'First']);
        $business->contacts()->create(['name' => 'Supplier', 'role' => 'supplier', 'trading_name' => 'Supply Co', 'billing_country' => 'GB']);

        (require database_path(self::MIGRATION))->down();

        $this->assertSame(
            [0, 1, 'Supply Co', 'United Kingdom'],
            array_values(array_map(fn ($value) => is_bool($value) ? (int) $value : $value, (array) DB::table('contacts')->first(['is_customer', 'is_vendor', 'company_name', 'country']))),
        );
        $this->assertFalse(Schema::hasColumn('contacts', 'contact_number'));
        $this->assertSame(0, DB::table('workspace_sequences')->where('name', 'contact')->count());
    }
}
