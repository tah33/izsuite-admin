<?php

namespace Tests\Feature;

use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\User;
use App\Models\User\WorkspaceFile;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * A contact's attachments (Doc 03 sections 5 and 9): private files, kept in
 * the business's own folder and downloaded only through a short-lived link.
 */
class ContactAttachmentsApiTest extends TestCase
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

    private function contactOf(User $user, string $name = 'Uddin Traders Ltd'): Contact
    {
        return $this->businessOf($user)->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => $name]);
    }

    private function attach(Contact $contact, array $files)
    {
        return $this->post("/api/v1/contacts/{$contact->id}/attachments", ['files' => $files], ['Accept' => 'application/json']);
    }

    private function pdf(string $name = 'invoice.pdf', int $kilobytes = 120): UploadedFile
    {
        return UploadedFile::fake()->create($name, $kilobytes, 'application/pdf');
    }

    /* ---------------------------------------------------------- access */

    public function test_every_endpoint_needs_a_token_and_a_business(): void
    {
        $contact = $this->contactOf(User::factory()->create());

        $this->getJson("/api/v1/contacts/{$contact->id}/attachments")->assertUnauthorized();
        $this->attach($contact, [$this->pdf()])->assertUnauthorized();
        $this->deleteJson("/api/v1/contacts/{$contact->id}/attachments/1")->assertUnauthorized();
        $this->getJson('/api/v1/files/1/link')->assertUnauthorized();

        Sanctum::actingAs(User::factory()->create());

        $this->getJson("/api/v1/contacts/{$contact->id}/attachments")->assertNotFound()->assertJsonPath('message', 'Create a workspace first.');
        $this->assertSame(0, WorkspaceFile::count());
    }

    /* ---------------------------------------------------------- adding */

    public function test_attachments_are_added_kept_private_and_listed(): void
    {
        $user     = $this->signIn();
        $business = $this->businessOf($user);
        $contact  = $this->contactOf($user);

        $this->attach($contact, [$this->pdf(), UploadedFile::fake()->image('shop-front.png', 300, 200)])
            ->assertCreated()
            ->assertJsonPath('message', '2 attachments added.')
            ->assertJsonPath('data.0.name', 'invoice.pdf')
            ->assertJsonPath('data.0.size', 120 * 1024)
            ->assertJsonPath('data.0.mime_type', 'application/pdf')
            // Private: no address anyone could open.
            ->assertJsonPath('data.0.url', null)
            ->assertJsonPath('data.0.uploaded_by.name', $user->name)
            ->assertJsonPath('data.1.name', 'shop-front.png');

        foreach (WorkspaceFile::all() as $file) {
            $this->assertSame(['local', 'private', 'attachments'], [$file->disk, $file->visibility, $file->collection->value]);
            $this->assertStringStartsWith("workspaces/{$business->id}/attachments/", $file->path);
            Storage::disk('local')->assertExists($file->path);
        }

        $this->getJson("/api/v1/contacts/{$contact->id}/attachments")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'invoice.pdf');
    }

    public function test_only_documents_and_pictures_up_to_10_mb_ten_at_a_time(): void
    {
        $contact = $this->contactOf($this->signIn());

        $this->attach($contact, [UploadedFile::fake()->create('setup.exe', 10, 'application/x-msdownload')])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0' => 'Attach PDFs, pictures, Word, Excel or PowerPoint files, CSV or text files, or ZIP archives.']);

        $this->attach($contact, [$this->pdf('big.pdf', 10 * 1024 + 1)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files.0' => 'Files can be up to 10 MB.']);

        $this->attach($contact, array_map(fn (int $n) => $this->pdf("page-{$n}.pdf", 1), range(1, 11)))
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['files' => 'Upload up to ten files at a time.']);

        $this->post("/api/v1/contacts/{$contact->id}/attachments", [], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('files');

        $this->assertSame(0, WorkspaceFile::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    /* ---------------------------------------------------------- downloading */

    public function test_a_private_file_downloads_only_through_a_short_lived_link(): void
    {
        $contact = $this->contactOf($this->signIn());
        $id      = $this->attach($contact, [UploadedFile::fake()->createWithContent('Price list.txt', 'Chairs 149.99')])->json('data.0.id');

        $link = $this->getJson("/api/v1/files/{$id}/link")
            ->assertOk()
            ->assertJsonPath('data.name', 'Price list.txt')
            ->json('data.url');

        $this->assertStringContainsString("/api/v1/files/{$id}/download?expires=", $link);

        // No token needed - the signature is the permission.
        $this->app['auth']->forgetGuards();

        $download = $this->get($link)->assertOk();
        $this->assertSame('Chairs 149.99', $download->streamedContent());
        $this->assertStringContainsString('attachment; filename="Price list.txt"', $download->headers->get('Content-Disposition'));

        $this->get($link.'0')->assertForbidden();
        $this->get("/api/v1/files/{$id}/download")->assertForbidden();

        $this->travel(6)->minutes();
        $this->get($link)->assertForbidden();
    }

    /* ---------------------------------------------------------- removing */

    public function test_an_attachment_is_removed_with_its_file(): void
    {
        $contact = $this->contactOf($this->signIn());
        $file    = WorkspaceFile::find($this->attach($contact, [$this->pdf()])->json('data.0.id'));

        $this->deleteJson("/api/v1/contacts/{$contact->id}/attachments/{$file->id}")
            ->assertOk()
            ->assertJsonPath('message', 'Attachment removed.');

        $this->assertDatabaseMissing('workspace_files', ['id' => $file->id]);
        Storage::disk('local')->assertMissing($file->path);
    }

    public function test_deleting_a_contact_deletes_its_attachments(): void
    {
        $contact = $this->contactOf($this->signIn());
        $this->attach($contact, [$this->pdf()])->assertCreated();
        $path = WorkspaceFile::value('path');

        $this->deleteJson("/api/v1/contacts/{$contact->id}")->assertOk();

        $this->assertSame(0, WorkspaceFile::count());
        Storage::disk('local')->assertMissing($path);
    }

    /* ---------------------------------------------------------- isolation */

    public function test_another_business_contact_or_file_is_not_found(): void
    {
        $user    = $this->signIn();
        $mine    = $this->contactOf($user);
        $other   = $this->contactOf($user, 'Karim Stores');
        $sibling = $user->workspaces()->create(['name' => 'Second Business'])->contacts()->create(['contact_type' => 'business', 'role' => 'customer', 'name' => 'Theirs']);

        $otherFile = $this->attach($other, [$this->pdf()])->json('data.0.id');

        $this->getJson("/api/v1/contacts/{$sibling->id}/attachments")->assertNotFound()->assertJsonPath('message', 'Contact not found.');
        $this->attach($sibling, [$this->pdf()])->assertNotFound();

        // A file of another contact is not this one's to remove.
        $this->deleteJson("/api/v1/contacts/{$mine->id}/attachments/{$otherFile}")->assertNotFound()->assertJsonPath('message', 'File not found.');

        // Nor is another business's file linked to.
        $this->signIn(User::factory()->create());
        $this->getJson("/api/v1/files/{$otherFile}/link")->assertNotFound()->assertJsonPath('message', 'File not found.');

        $this->assertSame(1, WorkspaceFile::count());
    }

    public function test_each_change_is_logged_once_by_name(): void
    {
        $user    = $this->signIn();
        $contact = $this->contactOf($user);

        $id = $this->attach($contact, [$this->pdf()])->json('data.0.id');
        $this->deleteJson("/api/v1/contacts/{$contact->id}/attachments/{$id}")->assertOk();

        $this->assertSame(
            ['Attached "invoice.pdf" to contact "Uddin Traders Ltd"', 'Removed attachment "invoice.pdf" from contact "Uddin Traders Ltd"'],
            ActivityLog::where('user_id', $user->id)->orderBy('id')->pluck('description')->all(),
        );
    }
}
