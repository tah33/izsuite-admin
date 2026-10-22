<?php

namespace Tests\Feature;

use App\Models\User\User;
use App\Models\User\Workspace;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The migration that gave locations, business settings and staff a workspace:
 * rows saved before it - owned by the account alone - must land in the
 * account's oldest workspace, and an account with no workspace gets one.
 */
class BusinessDataWorkspaceMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2025_01_01_000067_give_business_data_a_workspace.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
    }

    /** The account's own rows, as they were saved before the migration. */
    private function legacyRowsFor(User $user, ?string $businessName = null): void
    {
        $now = now();

        DB::table('locations')->insert(['user_id' => $user->id, 'name' => 'Main Location', 'created_at' => $now, 'updated_at' => $now]);
        DB::table('user_staff')->insert(['user_id' => $user->id, 'name' => 'Owner', 'status' => 1, 'created_at' => $now, 'updated_at' => $now]);

        if ($businessName) {
            DB::table('business_settings')->insert([
                'user_id'                    => $user->id,
                'business_name'              => $businessName,
                'currency'                   => 'USD',
                'language'                   => 'en',
                'country'                    => 'Bangladesh',
                'state'                      => 'Dhaka',
                'city'                       => 'Dhaka',
                'zip_code'                   => '1207',
                'landmark'                   => 'Near City Center',
                'timezone'                   => 'Asia/Dhaka',
                'financial_year_start_month' => 7,
                'stock_accounting_method'    => 'fifo',
                'created_at'                 => $now,
                'updated_at'                 => $now,
            ]);
        }
    }

    private function workspaceIdsIn(string $table, User $user): array
    {
        return DB::table($table)->where('user_id', $user->id)->pluck('workspace_id')->unique()->values()->all();
    }

    public function test_existing_rows_move_to_their_accounts_oldest_workspace(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->down();

        $withTwo = User::factory()->create();
        $oldest  = $withTwo->workspaces()->create(['name' => 'Oldest']);
        $withTwo->workspaces()->create(['name' => 'Newer']);
        $this->legacyRowsFor($withTwo, 'Two Shops Ltd');

        $migration->up();

        foreach (['locations', 'user_staff', 'business_settings'] as $table) {
            $this->assertSame([$oldest->id], $this->workspaceIdsIn($table, $withTwo), $table);
        }

        $this->assertSame(2, $withTwo->workspaces()->count());
    }

    public function test_an_account_with_data_but_no_workspace_gets_one_named_after_its_business(): void
    {
        $migration = require database_path(self::MIGRATION);
        $migration->down();

        $named   = User::factory()->create();
        $unnamed = User::factory()->create();
        $this->legacyRowsFor($named, 'Amina Traders');
        $this->legacyRowsFor($unnamed);

        $migration->up();

        $namedBusiness = Workspace::where('user_id', $named->id)->sole();
        $this->assertSame('Amina Traders', $namedBusiness->name);
        $this->assertSame('starter', $namedBusiness->package->key);

        $this->assertSame('Billing Starter', Workspace::where('user_id', $unnamed->id)->sole()->name);

        foreach (['locations', 'user_staff', 'business_settings'] as $table) {
            $this->assertSame([$namedBusiness->id], $this->workspaceIdsIn($table, $named), $table);
        }
    }

    public function test_rolling_back_keeps_one_set_of_settings_per_account(): void
    {
        $user   = User::factory()->create();
        $first  = $user->workspaces()->create(['name' => 'First']);
        $second = $user->workspaces()->create(['name' => 'Second']);

        foreach ([$first, $second] as $business) {
            $business->businessSetting()->create([
                'user_id'                    => $user->id,
                'business_name'              => $business->name,
                'currency'                   => 'USD',
                'language'                   => 'en',
                'country'                    => 'Bangladesh',
                'state'                      => 'Dhaka',
                'city'                       => 'Dhaka',
                'zip_code'                   => '1207',
                'landmark'                   => 'Near City Center',
                'timezone'                   => 'Asia/Dhaka',
                'financial_year_start_month' => 7,
                'stock_accounting_method'    => 'fifo',
            ]);
        }

        (require database_path(self::MIGRATION))->down();

        $this->assertSame(['First'], DB::table('business_settings')->where('user_id', $user->id)->pluck('business_name')->all());
    }
}
