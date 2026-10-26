<?php

namespace Tests\Feature;

use App\Models\User\Contact;
use App\Models\User\Item;
use App\Models\User\User;
use App\Models\User\WorkspaceFile;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Where businesses' files go - this server or DigitalOcean Spaces, by
 * FILES_DISK - and moving them across (files:move). Spaces is faked: a disk
 * named "spaces" that answers like one, temporary links included.
 */
class FileStorageSwitchTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    private Contact $contact;

    private Item $item;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        Storage::fake('local');
        Storage::fake('public');
        Storage::fake('spaces');
        Storage::disk('spaces')->buildTemporaryUrlsUsing(fn (string $path, $expiration, array $options) => "https://izsuite.sgp1.digitaloceanspaces.test/{$path}?expires={$expiration->getTimestamp()}&disposition=".rawurlencode($options['ResponseContentDisposition'] ?? ''));

        $this->user    = $this->signIn();
        $this->contact = $this->businessOf($this->user)->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Uddin Traders Ltd']);
        $this->item    = $this->businessOf($this->user)->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Office Chair', 'unit' => 'Piece']);
    }

    private function attach(string $name): WorkspaceFile
    {
        $id = $this->post("/api/v1/contacts/{$this->contact->id}/attachments", ['files' => [UploadedFile::fake()->createWithContent($name, "contents of {$name}")]], ['Accept' => 'application/json'])
            ->assertCreated()
            ->json('data.0.id');

        return WorkspaceFile::find($id);
    }

    private function saveImage(): WorkspaceFile
    {
        $this->post("/api/v1/items/{$this->item->id}/image", ['image' => UploadedFile::fake()->image('chair.png', 400, 300)], ['Accept' => 'application/json'])->assertOk();

        return $this->item->image()->sole();
    }

    public function test_new_files_go_to_spaces_once_switched_and_the_old_ones_keep_working(): void
    {
        $before = $this->attach('before.txt');

        config(['files.storage' => 'spaces']);

        $after = $this->attach('after.txt');
        $image = $this->saveImage();

        $this->assertSame(['local', 'spaces', 'spaces'], [$before->disk, $after->disk, $image->disk]);
        Storage::disk('spaces')->assertExists([$after->path, $image->path, $image->thumbnail_path]);

        // A private file on Spaces downloads straight from it, for a few minutes.
        $link = $this->getJson("/api/v1/files/{$after->id}/link")->json('data.url');
        $this->assertStringStartsWith("https://izsuite.sgp1.digitaloceanspaces.test/{$after->path}?expires=", $link);
        $this->assertStringContainsString(rawurlencode('attachment; filename=after.txt'), $link);

        // The one stored here before the switch still downloads from here.
        $this->assertStringContainsString("/api/v1/files/{$before->id}/download?expires=", $this->getJson("/api/v1/files/{$before->id}/link")->json('data.url'));
        $this->get($this->getJson("/api/v1/files/{$before->id}/link")->json('data.url'))->assertOk();

        // A public one is read from the Space's own address.
        $this->assertSame(Storage::disk('spaces')->url($image->path), $this->getJson("/api/v1/items/{$this->item->id}")->json('data.image.url'));
    }

    public function test_files_move_to_spaces_and_back(): void
    {
        $attachment = $this->attach('price-list.txt');
        $image      = $this->saveImage();

        $this->artisan('files:move', ['--to' => 'spaces', '--dry-run' => true])
            ->expectsOutput('Would move 2 file(s) to spaces; 0 already there; 0 failed.')
            ->assertSuccessful();

        $this->assertSame('local', $attachment->fresh()->disk);

        $this->artisan('files:move', ['--to' => 'spaces'])
            ->expectsOutput('Moved 2 file(s) to spaces; 0 already there; 0 failed.')
            ->assertSuccessful();

        $this->assertSame(['spaces', 'spaces'], [$attachment->fresh()->disk, $image->fresh()->disk]);
        Storage::disk('spaces')->assertExists([$attachment->path, $image->path, $image->thumbnail_path]);
        Storage::disk('local')->assertMissing($attachment->path);
        Storage::disk('public')->assertMissing([$image->path, $image->thumbnail_path]);
        $this->assertSame('contents of price-list.txt', Storage::disk('spaces')->get($attachment->path));

        $this->artisan('files:move', ['--to' => 'spaces'])
            ->expectsOutput('Moved 0 file(s) to spaces; 2 already there; 0 failed.')
            ->assertSuccessful();

        // Back again: private files to the private disk, public ones to the public one.
        $this->artisan('files:move', ['--to' => 'local'])->assertSuccessful();

        $this->assertSame(['local', 'public'], [$attachment->fresh()->disk, $image->fresh()->disk]);
        Storage::disk('local')->assertExists($attachment->path);
        Storage::disk('public')->assertExists([$image->path, $image->thumbnail_path]);
        Storage::disk('spaces')->assertMissing([$attachment->path, $image->path]);
    }

    public function test_files_move_needs_to_know_where(): void
    {
        $this->artisan('files:move', ['--to' => 'dropbox'])
            ->expectsOutput('Say where to move the files: --to=local or --to=spaces.')
            ->assertExitCode(2);
    }
}
