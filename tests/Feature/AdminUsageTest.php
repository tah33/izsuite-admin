<?php

namespace Tests\Feature;

use App\Models\Admin\Role;
use App\Models\Billing\Package;
use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Models\User\WorkspaceMember;
use App\Models\User\WorkspaceUsageSnapshot;
use App\Services\User\Workspaces\SetUpNewWorkspace;
use App\Support\BusinessStatus;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * Doc 15 Phase D2 (section 15.18): Admin > Usage - each business's users,
 * active locations, storage and apps against its plan's limits, the share
 * used and where it stands (a limit of one at one is no warning), sorted
 * and filtered in SQL; and its history, from the snapshot usage:snapshot
 * keeps each day.
 */
class AdminUsageTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    /** @var array<string, Workspace> */
    private array $business = [];

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);
        $this->travelTo(Carbon::parse('2026-11-14 10:00:00', 'UTC'));
        // The Overview's alerts ask the PDF maker and write a probe file (Phase D3a).
        Http::fake(['*/health' => Http::response(['status' => 'up'])]);
        Storage::fake((string) config('files.storage'));
        $this->admin = User::factory()->create(['role_id' => Role::SUPER_ADMIN_ID]);

        Package::where('key', 'standard')->update(['user_limit' => 5, 'location_limit' => 3, 'storage_limit_mb' => 10]);
        Package::where('key', 'premium')->update(['user_limit' => null, 'location_limit' => null, 'storage_limit_mb' => null]);

        // Karim: 4 of 5 users and 9 of 10 MB - nearing both. Rahim: 6 of 5 users, 3 of 3 locations - past one, at the other.
        // Uddin: Billing Starter's one user, its owner with no member row - no warning. Nadia: Premium, without limits.
        $this->business('karim', 'standard', users: 4, locations: 1, megabytes: 9);
        $this->business('rahim', 'standard', users: 6, locations: 3, megabytes: 2);
        $this->business('uddin', 'starter', users: 0, locations: 0, megabytes: 0);
        $this->business('nadia', 'premium', users: 2, locations: 1, megabytes: 1);
        $this->business('shut', 'standard', users: 1, locations: 1, megabytes: 0);
        Workspace::whereKey($this->business['shut']->id)->update(['status' => BusinessStatus::CLOSED]);
    }

    /** A business on $plan with $users counted (the owner one of them; none: no member rows), $locations active and $megabytes of files. */
    private function business(string $name, string $plan, int $users, int $locations, int $megabytes): void
    {
        $owner    = User::factory()->create(['first_name' => ucfirst($name)]);
        $business = $owner->workspaces()->create(['name' => ucfirst($name).' Stores']);
        Workspace::whereKey($business->id)->update(['package_id' => Package::where('key', $plan)->value('id')]);

        if ($users > 0) {
            app(SetUpNewWorkspace::class)->addRoles($business, $owner, 'api');
            $this->members($business, $users - 1);
        }

        for ($i = 0; $i < $locations; $i++) {
            $business->locations()->create(['user_id' => $owner->id, 'name' => "Shop {$i}", 'code' => "S{$i}"]);
        }

        if ($megabytes > 0) {
            WorkspaceFile::create([
                'workspace_id' => $business->id, 'attachable_type' => Workspace::class, 'attachable_id' => $business->id, 'collection' => 'attachments',
                'disk' => 'local', 'path' => 'files/'.Str::random(8), 'visibility' => 'private', 'name' => 'scan.pdf', 'size' => $megabytes * 1048576,
            ]);
        }

        $this->business[$name] = $business->fresh();
    }

    private function members(Workspace $business, int $count): void
    {
        for ($i = 0; $i < $count; $i++) {
            $user = User::factory()->create();
            $business->members()->create([
                'user_id' => $user->id, 'role_id' => $business->roles()->where('name', 'Sales User')->value('id'),
                'name'    => $user->name, 'email' => $user->email, 'status' => $i === 0 ? WorkspaceMember::PENDING : WorkspaceMember::ACTIVE,
                'location_access' => WorkspaceMember::ALL_LOCATIONS,
            ]);
        }
    }

    private function usage(array $query = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->admin)->get(route('admin.usage.index', $query))->assertOk();
    }

    /** @return list<string> the businesses listed, by name, in order */
    private function names(TestResponse $page): array
    {
        return collect($page->viewData('rows')->items())->map(fn (array $row) => $row['business']->name)->all();
    }

    public function test_it_lists_each_business_against_its_limits_sorted_and_filtered(): void
    {
        $page = $this->usage();
        $rows = collect($page->viewData('rows')->items())->keyBy(fn (array $row) => $row['business']->name);

        $karim = $rows['Karim Stores']['usage'];
        $this->assertSame(['used' => 4, 'limit' => 5, 'percent' => 80.0, 'state' => 'warning'], $karim['users']);
        $this->assertSame(['used' => 9 * 1048576, 'limit' => 10 * 1048576, 'percent' => 90.0, 'state' => 'warning'], $karim['storage']);
        $this->assertSame(['warning', 'ok'], [$karim['state'], $karim['locations']['state']]);
        $this->assertSame(['over', 'full', 'over'], [$rows['Rahim Stores']['usage']['users']['state'], $rows['Rahim Stores']['usage']['locations']['state'], $rows['Rahim Stores']['usage']['state']]);
        // The owner counts without a member row; one of one is no warning.
        $this->assertSame(['used' => 1, 'limit' => 1, 'percent' => 100.0, 'state' => 'ok'], $rows['Uddin Stores']['usage']['users']);
        $this->assertSame([null, null, 'ok'], [$rows['Nadia Stores']['usage']['users']['limit'], $rows['Nadia Stores']['usage']['users']['percent'], $rows['Nadia Stores']['usage']['state']]);

        // Closest to a limit first - a limit of one left out - then the newest.
        $this->assertSame(['Rahim Stores', 'Karim Stores', 'Shut Stores', 'Nadia Stores', 'Uddin Stores'], $this->names($page));
        $page->assertSeeInOrder(['Rahim Stores', '6 / 5', 'Past its limit', 'Karim Stores', '4 / 5', '9 MB / 10 MB', 'Nearing its limit']);

        $this->assertSame(['Rahim Stores', 'Karim Stores'], $this->names($this->usage(['state' => 'warning'])));
        $this->assertSame(['Rahim Stores'], $this->names($this->usage(['state' => 'full'])));
        $this->assertSame(['Rahim Stores'], $this->names($this->usage(['state' => 'over', 'limit' => 'users'])));
        $this->assertSame(['Karim Stores'], $this->names($this->usage(['state' => 'warning', 'limit' => 'storage'])));
        $this->assertSame(['Rahim Stores'], $this->names($this->usage(['state' => 'full', 'limit' => 'locations'])));
        $this->assertSame([], $this->names($this->usage(['state' => 'warning', 'limit' => 'locations', 'package' => 'starter'])));
        $this->assertSame(['Nadia Stores'], $this->names($this->usage(['package' => 'premium'])));
        $this->assertSame(['Rahim Stores'], $this->names($this->usage(['search' => 'rahim'])));
        $this->assertSame('Karim Stores', $this->names($this->usage(['sort' => 'storage_bytes']))[0]);
        // By share of users: Billing Starter's one of one comes with those without a limit, last.
        $this->assertSame(['Rahim Stores', 'Karim Stores', 'Shut Stores', 'Nadia Stores', 'Uddin Stores'], $this->names($this->usage(['sort' => 'users'])));

        $this->actingAs($this->admin)->get(route('admin.usage.index', ['state' => 'nearly']))->assertSessionHasErrors('state');
    }

    public function test_a_snapshot_a_day_keeps_the_history_and_the_days_it_changed(): void
    {
        $this->artisan('usage:snapshot')->expectsOutput('Kept the usage of 4 businesses for 2026-11-14.')->assertSuccessful();
        $this->assertSame(0, WorkspaceUsageSnapshot::where('workspace_id', $this->business['shut']->id)->count());

        // Karim adds his fifth user the next day; nothing changes the day after, snapshotted twice.
        $this->travelTo(Carbon::parse('2026-11-15 23:50:00', 'UTC'));
        $this->members($this->business['karim'], 1);
        $this->artisan('usage:snapshot')->assertSuccessful();
        $this->travelTo(Carbon::parse('2026-11-16 23:50:00', 'UTC'));
        $this->artisan('usage:snapshot')->assertSuccessful();
        $this->artisan('usage:snapshot')->assertSuccessful();

        $karim = WorkspaceUsageSnapshot::where('workspace_id', $this->business['karim']->id)->orderBy('day')->get();
        $this->assertSame([['2026-11-14', 4], ['2026-11-15', 5], ['2026-11-16', 5]], $karim->map(fn ($day) => [$day->day->toDateString(), $day->users])->all());
        $this->assertSame([1, 9 * 1048576, 5, 3, 10], [$karim[0]->locations, $karim[0]->storage_bytes, $karim[0]->user_limit, $karim[0]->location_limit, $karim[0]->storage_limit_mb]);

        $page = $this->actingAs($this->admin)->get(route('admin.usage.show', [$this->business['karim']->id, 'from' => '2026-11-14', 'to' => '2026-11-16']))->assertOk();
        $this->assertSame(['Nov 14', 'Nov 15', 'Nov 16'], $page->viewData('chart')['labels']);
        $this->assertSame([4, 5, 5], $page->viewData('chart')['users']);
        $this->assertSame([9.0, 9.0, 9.0], $page->viewData('chart')['storage']);
        // Newest first, the first day always.
        $this->assertSame(['2026-11-15', '2026-11-14'], $page->viewData('changes')->map(fn ($day) => $day->day->toDateString())->all());
        $page->assertSeeInOrder(['Karim Stores', 'At its limit', 'Users', '5 / 5', 'History', 'Days It Changed', 'Nov 15, 2026', '5 / 5', 'Nov 14, 2026', '4 / 5']);

        // The last 90 days unless chosen; none before its first snapshot.
        $this->assertSame(['from' => '2026-08-19', 'to' => '2026-11-16'], $this->get(route('admin.usage.show', $this->business['karim']->id))->viewData('filters'));
        $this->get(route('admin.usage.show', [$this->business['karim']->id, 'to' => '2026-11-01']))->assertOk()->assertSee('No snapshot in these days yet.');
        $this->get(route('admin.usage.show', [$this->business['karim']->id, 'from' => '2026-11-16', 'to' => '2026-11-14']))->assertSessionHasErrors(['to' => 'The last day cannot come before the first.']);
        $this->get(route('admin.usage.show', 999999))->assertNotFound();
    }

    public function test_billing_support_and_read_only_administrators_open_usage(): void
    {
        $role = fn (string $slug) => User::factory()->create(['role_id' => Role::where('slug', $slug)->value('id')]);

        foreach (['billing-administrator', 'support-administrator', 'read-only-administrator'] as $slug) {
            $as = $role($slug);
            $this->usage(as: $as);
            $this->actingAs($as)->get(route('admin.usage.show', $this->business['karim']->id))->assertOk();
        }

        $this->actingAs($role('product-administrator'))->get(route('admin.usage.index'))->assertForbidden();
        $this->actingAs($this->admin)->get(route('admin.businesses.show', $this->business['karim']->id))->assertOk()->assertSee(route('admin.usage.show', $this->business['karim']->id));
        $this->actingAs($this->admin)->get(route('admin.overview'))->assertOk()->assertSee('Open Usage');
    }
}
