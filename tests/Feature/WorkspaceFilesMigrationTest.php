<?php

namespace Tests\Feature;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * The migration that made logos files of their business: a logo saved before
 * it - a path on the public disk - must come through counted and still shown,
 * and rolling back must give it back.
 */
class WorkspaceFilesMigrationTest extends TestCase
{
    use RefreshDatabase;

    private const MIGRATION = 'migrations/2025_01_01_000076_create_workspace_files_table.php';

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RoleSeeder::class);

        Storage::fake('public');
    }

    private function settingsRow(Workspace $business, ?string $logo): void
    {
        DB::table('business_settings')->insert([
            'user_id' => $business->user_id, 'workspace_id' => $business->id, 'business_name' => $business->name,
            'language' => 'en', 'logo' => $logo, 'country' => 'Bangladesh', 'state' => 'Dhaka', 'city' => 'Dhaka',
            'zip_code' => '1207', 'landmark' => 'Near City Center', 'timezone' => 'Asia/Dhaka',
            'financial_year_start_month' => 7, 'stock_accounting_method' => 'fifo',
        ]);
    }

    public function test_existing_logos_become_files_of_their_business(): void
    {
        $user     = User::factory()->create();
        $withPath = $user->workspaces()->create(['name' => 'With Path']);
        $withUrl  = $user->workspaces()->create(['name' => 'With URL']);
        $without  = $user->workspaces()->create(['name' => 'Without']);

        $migration = require database_path(self::MIGRATION);
        $migration->down();

        Storage::disk('public')->put('business-logos/a.png', str_repeat('a', 2048));
        Storage::disk('public')->put('business-logos/b.png', 'bb');

        $this->settingsRow($withPath, 'business-logos/a.png');
        $this->settingsRow($withUrl, 'http://localhost:8000/storage/business-logos/b.png');
        $this->settingsRow($without, null);

        $migration->up();

        $this->assertSame(
            [[$withPath->id, 'business-logos/a.png', 2048], [$withUrl->id, 'business-logos/b.png', 2]],
            WorkspaceFile::orderBy('workspace_id')->get()->map(fn (WorkspaceFile $file) => [$file->attachable_id, $file->path, $file->size])->all(),
        );

        $logo = $withPath->logo()->sole();
        $this->assertSame(['public', 'public', 'logo', Workspace::class, 'a.png'], [$logo->disk, $logo->visibility, $logo->collection->value, $logo->attachable_type, $logo->name]);
        $this->assertFalse(Schema::hasColumn('business_settings', 'logo'));
    }

    public function test_rolling_back_gives_the_settings_their_logo_column_again(): void
    {
        $business = User::factory()->create()->workspaces()->create(['name' => 'First']);
        $business->businessSetting()->create([
            'user_id' => $business->user_id, 'business_name' => 'First', 'language' => 'en', 'country' => 'Bangladesh',
            'state' => 'Dhaka', 'city' => 'Dhaka', 'zip_code' => '1207', 'landmark' => 'Near City Center',
            'timezone' => 'Asia/Dhaka', 'financial_year_start_month' => 7, 'stock_accounting_method' => 'fifo',
        ]);
        $business->files()->create([
            'workspace_id' => $business->id, 'collection' => 'logo', 'disk' => 'public', 'path' => 'workspaces/1/logo/x.png',
            'visibility' => 'public', 'name' => 'x.png', 'size' => 10,
        ]);

        (require database_path(self::MIGRATION))->down();

        $this->assertSame('workspaces/1/logo/x.png', DB::table('business_settings')->value('logo'));
        $this->assertFalse(Schema::hasTable('workspace_files'));
    }
}
