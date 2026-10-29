<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\WorkspaceFile;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A business's file storage against its package's limit (Doc 13 sections
 * 13.2 and 13.14): set in the admin, counted from every file the business
 * keeps. Over it, files stay downloadable and only new uploads are refused.
 */
class StorageLimitsTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Contact $contact;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        Storage::fake('local');
        Storage::fake('public');
        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);

        $this->user    = $this->signIn();
        $this->contact = $this->businessOf($this->user)->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders Ltd']);
    }

    private function limitStarterTo(?int $megabytes): void
    {
        Package::where('key', Package::STARTER)->update(['storage_limit_mb' => $megabytes]);
    }

    private function attach(int $kilobytes, string $name = 'file.pdf')
    {
        return $this->post("/api/v1/contacts/{$this->contact->id}/attachments", ['files' => [UploadedFile::fake()->create($name, $kilobytes, 'application/pdf')]], ['Accept' => 'application/json']);
    }

    private function usage(): array
    {
        return $this->getJson('/api/v1/business-settings/usage')->assertOk()->json('data');
    }

    public function test_billing_starter_starts_with_100_mb_and_the_others_without_a_limit(): void
    {
        $this->assertSame(['starter' => 100, 'standard' => null, 'premium' => null], Package::orderBy('rank')->pluck('storage_limit_mb', 'key')->all());

        $this->assertSame([
            'package'   => ['name' => 'Billing Starter'],
            'storage'   => ['used_bytes' => 0, 'limit_bytes' => 100 * 1024 * 1024, 'percent' => 0, 'state' => 'ok'],
            'users'     => ['used' => 1, 'limit' => 1],
            'locations' => ['used' => 0, 'limit' => 1],
        ], $this->usage());
    }

    public function test_every_file_counts_and_the_state_warns_before_the_limit(): void
    {
        $this->limitStarterTo(1);

        $this->attach(500)->assertCreated();
        $this->assertSame(['used_bytes' => 500 * 1024, 'percent' => 48.8, 'state' => 'ok'], array_intersect_key($this->usage()['storage'], array_flip(['used_bytes', 'percent', 'state'])));

        // The logo counts like any other file.
        $this->post('/api/v1/business-settings', [
            'business_name' => 'Uddin Traders', 'language' => 'en', 'country' => 'Bangladesh', 'state' => 'Dhaka', 'city' => 'Dhaka',
            'zip_code' => '1207', 'landmark' => 'Near City Center', 'timezone' => 'Asia/Dhaka', 'financial_year_start_month' => 7,
            'stock_accounting_method' => 'fifo', 'logo' => UploadedFile::fake()->image('logo.png')->size(350),
        ], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(['used_bytes' => 850 * 1024, 'state' => 'warning'], array_intersect_key($this->usage()['storage'], array_flip(['used_bytes', 'state'])));
    }

    public function test_an_upload_the_business_has_no_room_for_is_refused(): void
    {
        $this->limitStarterTo(1);

        $this->attach(700)->assertCreated();

        $this->attach(700, 'second.pdf')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files' => 'This needs 700 KB, but only 324 KB of your 1 MB storage is left.']);

        $this->assertSame(['file.pdf'], WorkspaceFile::pluck('name')->all());
        $this->assertCount(1, Storage::disk('local')->allFiles());
    }

    public function test_over_the_limit_files_stay_downloadable_and_uploads_wait_for_room(): void
    {
        $this->limitStarterTo(2);
        $id = $this->attach(1536)->json('data.0.id');

        // The plan's limit is lowered below what the business keeps.
        $this->limitStarterTo(1);

        $this->assertSame(['percent' => 150, 'state' => 'full'], array_intersect_key($this->usage()['storage'], array_flip(['percent', 'state'])));

        $this->getJson("/api/v1/files/{$id}/link")->assertOk();

        $this->attach(1, 'tiny.pdf')
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files' => 'Your storage is full: 1.5 MB of 1 MB used. Delete files you no longer need, or move to a package with more storage.']);

        $this->deleteJson("/api/v1/contacts/{$this->contact->id}/attachments/{$id}")->assertOk();

        $this->assertSame('ok', $this->usage()['storage']['state']);
        $this->attach(1, 'tiny.pdf')->assertCreated();
    }

    public function test_a_replaced_image_makes_room_for_its_replacement(): void
    {
        $this->limitStarterTo(1);
        $item = $this->businessOf($this->user)->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Office Chair', 'unit' => 'Piece']);

        $this->post("/api/v1/items/{$item->id}/image", ['image' => UploadedFile::fake()->image('a.png')->size(600)], ['Accept' => 'application/json'])->assertOk();
        $this->post("/api/v1/items/{$item->id}/image", ['image' => UploadedFile::fake()->image('b.png')->size(600)], ['Accept' => 'application/json'])->assertOk();

        $this->assertSame(600 * 1024, $this->usage()['storage']['used_bytes']);
    }

    public function test_a_package_without_a_limit_takes_any_amount(): void
    {
        $this->limitStarterTo(null);

        $this->attach(5000)->assertCreated();

        $this->assertSame(['used_bytes' => 5000 * 1024, 'limit_bytes' => null, 'percent' => null, 'state' => 'ok'], $this->usage()['storage']);
    }

    public function test_the_admin_sets_each_package_storage_limit(): void
    {
        $admin   = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);
        $package = Package::where('key', 'standard')->sole();
        $fields  = ['name' => $package->name, 'description' => $package->description, 'is_active' => 1];

        $this->actingAs($admin)->putJson(route('admin.packages.update', $package->id), [...$fields, 'storage_limit_mb' => 5120])->assertOk();
        $this->assertSame(5120, $package->fresh()->storage_limit_mb);

        $this->actingAs($admin)->putJson(route('admin.packages.update', $package->id), [...$fields, 'storage_limit_mb' => ''])->assertOk();
        $this->assertNull($package->fresh()->storage_limit_mb);

        $this->actingAs($admin)->putJson(route('admin.packages.update', $package->id), [...$fields, 'storage_limit_mb' => 0])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('storage_limit_mb');

        $this->actingAs($admin)->get(route('admin.packages.index'))->assertOk()->assertSee('100 MB')->assertSee('No limit');
    }
}
