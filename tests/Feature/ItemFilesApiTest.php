<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
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
 * An item's image - public, with a thumbnail for lists - and a digital
 * product's files - private, like attachments (Doc 04 sections 6 and 12).
 */
class ItemFilesApiTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        Storage::fake('local');
        Storage::fake('public');
    }

    private function itemOf(User $user, array $overrides = []): Item
    {
        return $this->businessOf($user)->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Office Chair', 'unit' => 'Piece', ...$overrides]);
    }

    private function saveImage(Item $item, UploadedFile $image)
    {
        return $this->post("/api/v1/items/{$item->id}/image", ['image' => $image], ['Accept' => 'application/json']);
    }

    private function addFiles(Item $item, array $files)
    {
        return $this->post("/api/v1/items/{$item->id}/files", ['files' => $files], ['Accept' => 'application/json']);
    }

    /* ---------------------------------------------------------- the image */

    public function test_an_image_is_saved_with_a_thumbnail_and_shown_in_the_list(): void
    {
        $user = $this->signIn();
        $item = $this->itemOf($user);

        $response = $this->saveImage($item, UploadedFile::fake()->image('chair.png', 800, 600))
            ->assertOk()
            ->assertJsonPath('message', 'Image saved.')
            ->assertJsonPath('data.id', $item->id);

        $file = WorkspaceFile::sole();

        $this->assertSame(['public', 'public', 'image'], [$file->disk, $file->visibility, $file->collection->value]);
        Storage::disk('public')->assertExists([$file->path, $file->thumbnail_path]);
        $this->assertStringEndsWith('/storage/'.$file->path, $response->json('data.image.url'));
        $this->assertStringEndsWith('/storage/'.$file->thumbnail_path, $response->json('data.image.thumbnail_url'));

        // A small WebP, its longest side 240 pixels.
        [$width, $height, $type] = getimagesizefromstring(Storage::disk('public')->get($file->thumbnail_path));
        $this->assertSame([240, 180, IMAGETYPE_WEBP], [$width, $height, $type]);

        $this->getJson('/api/v1/items')->assertJsonPath('data.0.image.thumbnail_url', $response->json('data.image.thumbnail_url'));
    }

    public function test_a_new_image_replaces_the_old_one(): void
    {
        $item = $this->itemOf($this->signIn());

        $this->saveImage($item, UploadedFile::fake()->image('first.png'))->assertOk();
        $first = WorkspaceFile::sole();

        $this->saveImage($item, UploadedFile::fake()->image('second.jpg'))->assertOk();

        $this->assertSame('second.jpg', WorkspaceFile::sole()->name);
        Storage::disk('public')->assertMissing([$first->path, $first->thumbnail_path]);
    }

    public function test_an_image_is_removed(): void
    {
        $item = $this->itemOf($this->signIn());
        $this->saveImage($item, UploadedFile::fake()->image('chair.png'))->assertOk();
        $file = WorkspaceFile::sole();

        $this->deleteJson("/api/v1/items/{$item->id}/image")
            ->assertOk()
            ->assertJsonPath('message', 'Image removed.')
            ->assertJsonPath('data.image', null);

        Storage::disk('public')->assertMissing([$file->path, $file->thumbnail_path]);

        $this->deleteJson("/api/v1/items/{$item->id}/image")->assertNotFound()->assertJsonPath('message', 'This item has no image.');
    }

    public function test_an_image_is_a_jpg_png_or_webp_of_up_to_2_mb(): void
    {
        $item = $this->itemOf($this->signIn());

        $this->saveImage($item, UploadedFile::fake()->create('chair.pdf', 10, 'application/pdf'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image' => 'The image must be a JPG, PNG or WebP picture.']);

        $this->saveImage($item, UploadedFile::fake()->image('chair.png')->size(2049))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['image' => 'Files can be up to 2 MB.']);

        $this->assertSame(0, WorkspaceFile::count());
    }

    /* ---------------------------------------------------------- a digital product's files */

    public function test_a_digital_product_keeps_its_files_private(): void
    {
        $item = $this->itemOf($this->signIn(), ['type' => 'digital', 'name' => 'Design Course', 'unit' => 'Licence']);

        $this->addFiles($item, [UploadedFile::fake()->create('course.zip', 2048, 'application/zip'), UploadedFile::fake()->create('setup', 5, 'application/octet-stream')])
            ->assertCreated()
            ->assertJsonPath('message', '2 files added.')
            ->assertJsonPath('data.0.url', null);

        foreach (WorkspaceFile::all() as $file) {
            $this->assertSame(['local', 'private', 'digital_files'], [$file->disk, $file->visibility, $file->collection->value]);
        }

        $id = $this->getJson("/api/v1/items/{$item->id}/files")->assertOk()->assertJsonCount(2, 'data')->json('data.0.id');

        $this->assertStringContainsString("/api/v1/files/{$id}/download?expires=", $this->getJson("/api/v1/files/{$id}/link")->json('data.url'));
    }

    public function test_only_a_digital_product_takes_new_files_and_one_that_changes_keeps_them(): void
    {
        $user     = $this->signIn();
        $physical = $this->itemOf($user);
        $digital  = $this->itemOf($user, ['type' => 'digital', 'name' => 'Design Course', 'unit' => 'Licence']);

        $this->addFiles($physical, [UploadedFile::fake()->create('manual.pdf', 10, 'application/pdf')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files' => 'Only a digital product has files to deliver.']);

        $this->addFiles($digital, [UploadedFile::fake()->create('course.zip', 10, 'application/zip')])->assertCreated();

        $this->putJson("/api/v1/items/{$digital->id}", ['type' => 'service', 'usage' => 'both', 'name' => 'Design Course', 'unit' => 'Session'])->assertOk();

        $this->getJson("/api/v1/items/{$digital->id}/files")->assertJsonCount(1, 'data');
    }

    public function test_a_file_is_removed_and_deleting_the_item_removes_the_rest(): void
    {
        $item  = $this->itemOf($this->signIn(), ['type' => 'digital', 'name' => 'Design Course', 'unit' => 'Licence']);
        $ids   = $this->addFiles($item, [UploadedFile::fake()->create('a.zip', 10, 'application/zip'), UploadedFile::fake()->create('b.zip', 10, 'application/zip')])->json('data.*.id');
        $this->saveImage($item, UploadedFile::fake()->image('cover.png'))->assertOk();
        $paths = WorkspaceFile::pluck('path')->all();

        $this->deleteJson("/api/v1/items/{$item->id}/files/{$ids[0]}")->assertOk()->assertJsonPath('message', 'File removed.');
        $this->assertSame(2, WorkspaceFile::count());

        $this->deleteJson("/api/v1/items/{$item->id}")->assertOk();

        $this->assertSame(0, WorkspaceFile::count());
        Storage::disk('local')->assertMissing(array_slice($paths, 0, 2));
        Storage::disk('public')->assertMissing($paths[2]);
    }

    public function test_another_business_item_is_not_found(): void
    {
        $user    = $this->signIn();
        $sibling = $user->workspaces()->create(['name' => 'Second Business'])->items()->create(['type' => 'digital', 'usage' => 'both', 'name' => 'Theirs', 'unit' => 'Licence']);

        $this->saveImage($sibling, UploadedFile::fake()->image('x.png'))->assertNotFound()->assertJsonPath('message', 'Item not found.');
        $this->addFiles($sibling, [UploadedFile::fake()->create('x.zip', 1, 'application/zip')])->assertNotFound();
        $this->getJson("/api/v1/items/{$sibling->id}/files")->assertNotFound();

        $this->assertSame(0, WorkspaceFile::count());
    }

    public function test_each_change_is_logged_once_by_name(): void
    {
        $user = $this->signIn();
        $item = $this->itemOf($user, ['type' => 'digital', 'name' => 'Design Course', 'unit' => 'Licence']);

        $this->saveImage($item, UploadedFile::fake()->image('cover.png'))->assertOk();
        $this->deleteJson("/api/v1/items/{$item->id}/image")->assertOk();
        $id = $this->addFiles($item, [UploadedFile::fake()->create('course.zip', 10, 'application/zip')])->json('data.0.id');
        $this->deleteJson("/api/v1/items/{$item->id}/files/{$id}")->assertOk();

        $this->assertSame([
            'Added the image of item "Design Course"',
            'Removed the image of item "Design Course"',
            'Added file "course.zip" to item "Design Course"',
            'Removed file "course.zip" from item "Design Course"',
        ], ActivityLog::where('user_id', $user->id)->orderBy('id')->pluck('description')->all());
    }
}
