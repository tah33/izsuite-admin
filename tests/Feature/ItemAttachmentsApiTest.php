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
 * An item's attachments: documents kept with any item - private, like a
 * contact's, and apart from a digital product's files.
 */
class ItemAttachmentsApiTest extends TestCase
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

    private function attach(Item $item, array $files)
    {
        return $this->post("/api/v1/items/{$item->id}/attachments", ['files' => $files], ['Accept' => 'application/json']);
    }

    private function pdf(string $name = 'manual.pdf', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    public function test_any_item_keeps_its_attachments_private_and_lists_them(): void
    {
        $user = $this->signIn();
        $item = $this->itemOf($user);

        $this->attach($item, [$this->pdf(), UploadedFile::fake()->image('chair.png', 300, 200)])
            ->assertCreated()
            ->assertJsonPath('message', '2 attachments added.')
            ->assertJsonPath('data.0.name', 'manual.pdf')
            ->assertJsonPath('data.0.url', null)
            ->assertJsonPath('data.0.uploaded_by.name', $user->name);

        foreach (WorkspaceFile::all() as $file) {
            $this->assertSame(['local', 'private', 'attachments'], [$file->disk, $file->visibility, $file->collection->value]);
            Storage::disk('local')->assertExists($file->path);
        }

        $id   = $this->getJson("/api/v1/items/{$item->id}/attachments")->assertOk()->assertJsonCount(2, 'data')->json('data.0.id');

        $this->assertStringContainsString("/api/v1/files/{$id}/download?expires=", $this->getJson("/api/v1/files/{$id}/link")->json('data.url'));
    }

    public function test_only_documents_and_pictures_up_to_10_mb(): void
    {
        $item = $this->itemOf($this->signIn());

        $this->attach($item, [UploadedFile::fake()->create('setup.exe', 10, 'application/x-msdownload')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0' => 'Attach PDFs, pictures, Word, Excel or PowerPoint files, CSV or text files, or ZIP archives.']);

        $this->attach($item, [$this->pdf('big.pdf', 10 * 1024 + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0' => 'Files can be up to 10 MB.']);

        $this->assertSame(0, WorkspaceFile::count());
    }

    public function test_attachments_and_a_digital_products_files_are_kept_apart(): void
    {
        $item   = $this->itemOf($this->signIn(), ['type' => 'digital', 'name' => 'Design Course', 'unit' => 'Licence']);

        $fileId = $this->post("/api/v1/items/{$item->id}/files", ['files' => [UploadedFile::fake()->create('course.zip', 10, 'application/zip')]], ['Accept' => 'application/json'])->json('data.0.id');
        $this->attach($item, [$this->pdf('licence-terms.pdf')])->assertCreated();

        $this->getJson("/api/v1/items/{$item->id}/attachments")->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'licence-terms.pdf');
        $this->getJson("/api/v1/items/{$item->id}/files")->assertJsonCount(1, 'data')->assertJsonPath('data.0.name', 'course.zip');

        $this->deleteJson("/api/v1/items/{$item->id}/attachments/{$fileId}")->assertNotFound()->assertJsonPath('message', 'File not found.');
    }

    public function test_an_attachment_is_removed_and_deleting_the_item_removes_the_rest(): void
    {
        $item  = $this->itemOf($this->signIn());
        $ids   = $this->attach($item, [$this->pdf('a.pdf'), $this->pdf('b.pdf')])->json('data.*.id');
        $paths = WorkspaceFile::orderBy('id')->pluck('path')->all();

        $this->deleteJson("/api/v1/items/{$item->id}/attachments/{$ids[0]}")->assertOk()->assertJsonPath('message', 'Attachment removed.');
        Storage::disk('local')->assertMissing($paths[0]);

        $this->deleteJson("/api/v1/items/{$item->id}")->assertOk();

        $this->assertSame(0, WorkspaceFile::count());
        Storage::disk('local')->assertMissing($paths[1]);
    }

    public function test_another_business_item_is_not_found(): void
    {
        $user    = $this->signIn();
        $sibling = $user->workspaces()->create(['name' => 'Second Business'])->items()->create(['type' => 'physical', 'usage' => 'both', 'name' => 'Theirs', 'unit' => 'Piece']);

        $this->getJson("/api/v1/items/{$sibling->id}/attachments")->assertNotFound()->assertJsonPath('message', 'Item not found.');
        $this->attach($sibling, [$this->pdf()])->assertNotFound();

        $this->assertSame(0, WorkspaceFile::count());
    }

    public function test_each_change_is_logged_once_by_name(): void
    {
        $user = $this->signIn();
        $item = $this->itemOf($user);

        $id   = $this->attach($item, [$this->pdf()])->json('data.0.id');
        $this->deleteJson("/api/v1/items/{$item->id}/attachments/{$id}")->assertOk();

        $this->assertSame([
            'Attached "manual.pdf" to item "Office Chair"',
            'Removed attachment "manual.pdf" from item "Office Chair"',
        ], ActivityLog::where('user_id', $user->id)->orderBy('id')->pluck('description')->all());
    }
}
