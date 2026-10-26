<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use App\Models\Admin\Language;
use App\Models\Shared\ActivityLog;
use App\Models\User\Contact;
use App\Models\User\Import;
use App\Models\User\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\Concerns\SignsIntoBusinesses;
use Tests\TestCase;

/**
 * Contacts to and from CSV (Doc 03 section 15): a template, an export that
 * follows the list, and an import that is matched, checked - errors and
 * possible duplicates - and only then added.
 */
class ContactImportExportTest extends TestCase
{
    use RefreshDatabase;
    use SignsIntoBusinesses;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();

        // Seeded first, so super-admin holds id 1 and the owner is not taken for an admin.
        $this->seed(RoleSeeder::class);

        Storage::fake('local');
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Language::create(['name' => 'English', 'code' => 'en', 'is_active' => true]);

        $this->user = $this->signIn();
    }

    /** The rows of a downloaded CSV: its byte-order mark gone, each line split into cells. */
    private function csv(TestResponse $response): array
    {
        $content = $response->streamedContent();

        $this->assertStringStartsWith("\xEF\xBB\xBF", $content);

        return array_map(fn (string $line) => str_getcsv($line, ',', '"', ''), preg_split('/\r?\n/', trim(substr($content, 3))));
    }

    /** Row $index of a CSV, keyed by its header. */
    private function row(array $csv, int $index): array
    {
        return array_combine($csv[0], $csv[$index]);
    }

    private function upload(string $content, string $name = 'contacts.csv'): TestResponse
    {
        return $this->post('/api/v1/imports', ['kind' => 'contacts', 'file' => UploadedFile::fake()->createWithContent($name, $content)], ['Accept' => 'application/json']);
    }

    private function contactOf(array $fields): Contact
    {
        return $this->businessOf($this->user)->contacts()->create(['contact_type' => 'business', 'role' => 'customer', ...$fields]);
    }

    /* ---------------------------------------------------------- template and export */

    public function test_the_template_has_every_column_and_an_example_row(): void
    {
        $csv = $this->csv($this->get('/api/v1/imports/contacts/template', ['Accept' => 'application/json'])->assertOk()->assertDownload('contacts-import-template.csv'));

        $this->assertCount(34, $csv[0]);
        $this->assertSame(['Contact Type', 'Contact Role', 'Name'], array_slice($csv[0], 0, 3));

        $example = $this->row($csv, 1);
        $this->assertSame('Uddin Traders Ltd', $example['Name']);
        // Kept as text, so a spreadsheet does not read it as a formula.
        $this->assertSame("'+880 1711-000000", $example['Phone']);
    }

    public function test_the_export_follows_the_list_and_shows_tax_details_only_while_taxes_are_on(): void
    {
        $this->contactOf([
            'name' => 'Uddin Traders Ltd', 'phone' => '+880 1711-000000', 'tags' => ['Wholesale', 'Dhaka'],
            'billing_country' => 'BD', 'customer_payment_terms' => 30, 'tax_number' => 'BIN-1',
        ]);
        $this->contactOf(['name' => 'Karim Stores', 'role' => 'supplier', 'is_active' => false]);

        $csv = $this->csv($this->get('/api/v1/contacts/export?role=customer', ['Accept' => 'application/json'])->assertOk()->assertDownload('contacts-'.now()->format('Y-m-d').'.csv'));

        $this->assertCount(2, $csv);
        $this->assertNotContains('Tax ID', $csv[0]);
        $this->assertSame([
            'Contact Number' => 'C-00001', 'Contact Type' => 'Business', 'Contact Role' => 'Customer', 'Name' => 'Uddin Traders Ltd',
            'Phone' => "'+880 1711-000000", 'Tags' => 'Wholesale; Dhaka', 'Billing Country' => 'Bangladesh',
            'Shipping Same As Billing' => 'Yes', 'Customer Payment Terms' => '30', 'Status' => 'Active',
        ], array_intersect_key($this->row($csv, 1), array_flip(['Contact Number', 'Contact Type', 'Contact Role', 'Name', 'Phone', 'Tags', 'Billing Country', 'Shipping Same As Billing', 'Customer Payment Terms', 'Status'])));

        $this->businessOf($this->user)->forceFill(['tax_mode' => 'enabled'])->save();

        $csv = $this->csv($this->get('/api/v1/contacts/export', ['Accept' => 'application/json']));

        $this->assertCount(3, $csv);
        $this->assertSame(['Karim Stores', 'Inactive'], [$this->row($csv, 1)['Name'], $this->row($csv, 1)['Status']]);
        $this->assertSame('BIN-1', $this->row($csv, 2)['Tax ID']);

        $this->assertSame(['Exported 1 contact', 'Exported 2 contacts'], ActivityLog::where('user_id', $this->user->id)->orderBy('id')->pluck('description')->all());
    }

    /* ---------------------------------------------------------- import */

    public function test_an_import_is_matched_checked_and_then_added(): void
    {
        $existing = $this->contactOf(['name' => 'Old Name', 'email' => 'dup@existing.test']);

        $import = $this->upload(implode("\n", [
            'Name,Email,Phone,Type,Role,Country,Payment Terms,Tags',
            'Uddin Traders Ltd,accounts@uddin.test,+880 1711-000000,Business,Customer,Bangladesh,Net 30,Wholesale; Dhaka',
            'Karim Stores,,,,Supplier,BD,Due on Receipt,',
            ',nobody@example.test,,,,,,',
            'Existing Co,dup@existing.test,,,,,,',
            'Uddin Copy,ACCOUNTS@uddin.test,,,,,,',
            'Bad Country Ltd,,,,,Atlantis,,',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.status', 'mapping')
            ->assertJsonPath('data.row_count', 6)
            ->assertJsonPath('data.headers.6', 'Payment Terms')
            ->assertJsonPath('data.samples.0.0', 'Uddin Traders Ltd')
            ->json('data');

        // Matched by label, or by a word that means the field; "Payment Terms" could be either side's.
        $this->assertSame(
            ['contact_type' => 3, 'role' => 4, 'name' => 0, 'phone' => 2, 'email' => 1, 'tags' => 7, 'billing_country' => 5],
            array_filter($import['mapping'], fn ($column) => $column !== null),
        );

        $this->putJson("/api/v1/imports/{$import['id']}/mapping", ['mapping' => [...$import['mapping'], 'customer_payment_terms' => 6]])
            ->assertOk()
            ->assertJsonPath('data.status', 'ready')
            ->assertJsonPath('data.summary', ['rows' => 6, 'ready' => 2, 'duplicates' => 2, 'errors' => 2]);

        $errors = $this->getJson("/api/v1/imports/{$import['id']}/rows?show=errors")->assertOk()->json('data');
        $this->assertSame([4, 7], array_column($errors, 'line'));
        $this->assertSame('The contact name field is required.', $errors[0]['errors']['name']);
        $this->assertSame('"Atlantis" is not a country this list knows.', $errors[1]['errors']['billing_country']);

        $duplicates = $this->getJson("/api/v1/imports/{$import['id']}/rows?show=duplicates")->json('data');
        $this->assertSame([5, 6], array_column($duplicates, 'line'));
        $this->assertSame([['id' => $existing->id, 'number' => 'C-00001', 'name' => 'Old Name', 'matched_on' => ['email']]], $duplicates[0]['duplicates']);
        $this->assertSame([['row' => 2, 'matched_on' => ['email']]], $duplicates[1]['duplicates']);

        // Nothing is added before the run.
        $this->assertSame(1, Contact::count());

        $this->postJson("/api/v1/imports/{$import['id']}/run", ['include' => [6]])
            ->assertOk()
            ->assertJsonPath('data.status', 'done')
            ->assertJsonPath('data.result', ['created' => 3, 'skipped' => 3, 'failed' => 0]);

        $this->assertSame(['Old Name', 'Uddin Traders Ltd', 'Karim Stores', 'Uddin Copy'], Contact::orderBy('id')->pluck('name')->all());

        $uddin = Contact::where('name', 'Uddin Traders Ltd')->sole();
        $this->assertSame(
            ['C-00002', 'business', 'customer', '+880 1711-000000', 'accounts@uddin.test', 'BD', 30, ['Wholesale', 'Dhaka'], true, true],
            [$uddin->contact_number, $uddin->contact_type, $uddin->role, $uddin->phone, $uddin->email, $uddin->billing_country, $uddin->customer_payment_terms, $uddin->tags, $uddin->shipping_same_as_billing, $uddin->is_active],
        );
        $this->assertSame(['supplier', 'BD', 0], [Contact::where('name', 'Karim Stores')->value('role'), Contact::where('name', 'Karim Stores')->value('billing_country'), Contact::where('name', 'Karim Stores')->value('customer_payment_terms')]);

        $this->assertSame(['Imported 3 contacts from "contacts.csv"'], ActivityLog::where('user_id', $this->user->id)->pluck('description')->all());
        Storage::disk('local')->assertMissing(Import::find($import['id'])->file_path);

        $this->getJson("/api/v1/imports/{$import['id']}/rows?show=skipped")->assertJsonCount(3, 'data');
    }

    public function test_an_exported_file_imports_again_with_every_column_matched(): void
    {
        $this->businessOf($this->user)->forceFill(['tax_mode' => 'enabled'])->save();

        $original = $this->contactOf([
            'contact_type' => 'individual', 'role' => 'both', 'name' => 'Rahim Uddin', 'trading_name' => 'Uddin Traders',
            'phone' => '+880 1711-000000', 'alt_phone' => '02-9000000', 'email' => 'rahim@uddin.test', 'website' => 'https://uddin.test',
            'preferred_language' => 'en', 'tags' => ['VIP'], 'billing_line1' => 'House 12', 'billing_city' => 'Dhaka',
            'billing_country' => 'BD', 'shipping_same_as_billing' => false, 'shipping_line1' => 'Warehouse 3', 'shipping_country' => 'IN',
            'customer_payment_terms' => 15, 'supplier_payment_terms' => 0, 'tax_number' => 'BIN-1', 'tax_country' => 'BD',
            'notes' => "Calls before noon.\nPrefers e-mail.", 'is_active' => false,
        ]);

        $content = $this->get('/api/v1/contacts/export', ['Accept' => 'application/json'])->streamedContent();
        $import  = $this->upload($content)->assertCreated()->json('data');

        $this->assertNotContains(null, $import['mapping']);

        $this->putJson("/api/v1/imports/{$import['id']}/mapping", ['mapping' => $import['mapping']])
            ->assertJsonPath('data.summary', ['rows' => 1, 'ready' => 0, 'duplicates' => 1, 'errors' => 0]);

        $this->postJson("/api/v1/imports/{$import['id']}/run", ['include' => [2]])->assertJsonPath('data.result.created', 1);

        $copy   = Contact::latest('id')->first();
        $fields = array_diff(array_keys($original->getAttributes()), ['id', 'contact_number', 'created_at', 'updated_at']);

        $this->assertSame($original->fresh()->only($fields), $copy->only($fields));
    }

    public function test_a_file_must_have_a_header_rows_and_no_more_than_5000(): void
    {
        $rejected = [
            'This file is empty.'                                                     => '',
            'This file has a header row but no rows under it.'                        => "Name,Email\n",
            'A file can hold up to 5,000 rows. Split this one and import each part.'  => "Name\n".str_repeat("Someone\n", 5001),
        ];

        foreach ($rejected as $message => $content) {
            $this->upload($content)->assertUnprocessable()->assertJsonValidationErrors(['file' => $message]);
        }

        $this->post('/api/v1/imports', ['kind' => 'contacts', 'file' => UploadedFile::fake()->create('contacts.pdf', 10, 'application/pdf')], ['Accept' => 'application/json'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['file' => 'Import a CSV file. In Excel, use Save As and choose CSV (UTF-8).']);

        $this->assertSame(0, Import::count());
        $this->assertSame([], Storage::disk('local')->allFiles());
    }

    public function test_semicolons_and_windows_1252_are_read(): void
    {
        $import = $this->upload("Name;Email\r\nCaf\xE9 Rio;cafe@rio.test\r\n")->assertCreated()->json('data');

        $this->putJson("/api/v1/imports/{$import['id']}/mapping", ['mapping' => $import['mapping']])->assertJsonPath('data.summary.ready', 1);
        $this->postJson("/api/v1/imports/{$import['id']}/run")->assertJsonPath('data.result.created', 1);

        $this->assertSame(['Café Rio', 'cafe@rio.test'], [Contact::sole()->name, Contact::sole()->email]);
    }

    public function test_every_required_field_needs_a_column_and_a_column_fills_one_field(): void
    {
        $import = $this->upload("Name,Email\nUddin,u@uddin.test\n")->json('data');

        $this->putJson("/api/v1/imports/{$import['id']}/mapping", ['mapping' => [...$import['mapping'], 'name' => null]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mapping.name' => 'Match a column to Name: every row needs it.']);

        $this->putJson("/api/v1/imports/{$import['id']}/mapping", ['mapping' => [...$import['mapping'], 'trading_name' => 0]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mapping' => 'The column "Name" is matched to more than one field.']);

        $this->putJson("/api/v1/imports/{$import['id']}/mapping", ['mapping' => [...$import['mapping'], 'email' => 9]])
            ->assertUnprocessable()
            ->assertJsonValidationErrors(['mapping.email' => "Choose one of the file's columns."]);
    }

    public function test_an_import_runs_once_and_is_its_business_own(): void
    {
        $import = $this->upload("Name\nUddin\n")->json('data');

        $this->postJson("/api/v1/imports/{$import['id']}/run")->assertStatus(409)->assertJsonPath('message', 'Check the file before adding its rows.');

        $this->putJson("/api/v1/imports/{$import['id']}/mapping", ['mapping' => $import['mapping']])->assertOk();
        $this->postJson("/api/v1/imports/{$import['id']}/run")->assertOk();
        $this->postJson("/api/v1/imports/{$import['id']}/run")->assertStatus(409)->assertJsonPath('message', 'This import is finished. Start a new one to import another file.');

        $other = $this->upload("Name\nKarim\n")->json('data');

        $this->signIn(User::factory()->create());

        $this->getJson("/api/v1/imports/{$other['id']}")->assertNotFound()->assertJsonPath('message', 'Import not found.');
        $this->deleteJson("/api/v1/imports/{$other['id']}")->assertNotFound();
    }

    public function test_an_import_is_dropped_with_its_file(): void
    {
        $import = $this->upload("Name\nUddin\n")->json('data');
        $path   = Import::find($import['id'])->file_path;

        Storage::disk('local')->assertExists($path);

        $this->deleteJson("/api/v1/imports/{$import['id']}")->assertOk()->assertJsonPath('message', 'Import dropped.');

        $this->assertSame(0, Import::count());
        Storage::disk('local')->assertMissing($path);
    }
}
