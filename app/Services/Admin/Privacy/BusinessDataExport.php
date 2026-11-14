<?php

namespace App\Services\Admin\Privacy;

use App\Models\User\Workspace;
use App\Repositories\Admin\BusinessExportRepository;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\LazyCollection;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

/**
 * A business's data as a ZIP of CSV files (Doc 15 section 15.24 - Phase
 * G1), one file for each kind of record, with a README saying what each is.
 * The first row of each names its columns. Secrets - passwords, tokens, keys
 * - are left out, and so are the stored files themselves, which files.csv
 * lists. Kept on the private disk until the request's export expires.
 */
class BusinessDataExport
{
    /** Where the exports are kept, on the private disk. */
    public const DIRECTORY = 'privacy-exports';

    /** file => the business's own table */
    private const OWN = [
        'business-profile'     => 'business_settings',
        'locations'            => 'locations',
        'roles'                => 'workspace_roles',
        'contacts'             => 'contacts',
        'item-categories'      => 'item_categories',
        'item-brands'          => 'item_brands',
        'items'                => 'items',
        'sales-documents'      => 'sales_documents',
        'sales-payments'       => 'sales_payments',
        'purchases'            => 'purchases',
        'supplier-payments'    => 'supplier_payments',
        'expense-categories'   => 'expense_categories',
        'expenses'             => 'expenses',
        'expense-payments'     => 'expense_payments',
        'payment-accounts'     => 'payment_accounts',
        'account-transactions' => 'account_transactions',
        'account-transfers'    => 'account_transfers',
        'tax-rates'            => 'tax_rates',
        'withholding-taxes'    => 'withholding_taxes',
        'payment-terms'        => 'payment_terms',
        'exchange-rates'       => 'exchange_rates',
        'comments'             => 'record_comments',
        'files'                => 'workspace_files',
        'document-emails'      => 'document_emails',
        'billing-details'      => 'workspace_billing',
        'izsuite-invoices'     => 'subscription_invoices',
        'izsuite-payments'     => 'subscription_payments',
        'activity-log'         => 'activity_logs',
    ];

    /** file => [table, its column naming the parent, the parent table] */
    private const CHILDREN = [
        'item-components'      => ['item_components', 'combo_id', 'items'],
        'sales-document-lines' => ['sales_document_lines', 'sales_document_id', 'sales_documents'],
        'purchase-lines'       => ['purchase_lines', 'purchase_id', 'purchases'],
        'expense-lines'        => ['expense_lines', 'expense_id', 'expenses'],
    ];

    /** Columns never exported, by a part of their name. */
    private const SECRET = '/password|secret|token|credential|remember/i';

    public function __construct(
        private BusinessExportRepository $rows,
    ) {}

    /**
     * Builds $workspace's export, named after $name, on the private disk.
     *
     * @return array{path: string, size: int, files: int}
     */
    public function build(Workspace $workspace, string $name): array
    {
        $disk = Storage::disk('local');
        $path = self::DIRECTORY.'/'.$name.'-'.Str::random(24).'.zip';
        $disk->makeDirectory(self::DIRECTORY);

        $zip   = new ZipArchive;
        $temps = [];

        if ($zip->open($disk->path($path), ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            throw new RuntimeException("The export's file could not be made at {$path}.");
        }

        try {
            foreach ($this->sources($workspace) as $file => [$columns, $rows]) {
                $temps[] = $temp = $this->csv($columns, $rows);
                $zip->addFile($temp, "{$file}.csv");
            }

            $zip->addFromString('README.txt', $this->readme($workspace));
            $zip->close();
        } finally {
            array_map('unlink', array_filter($temps, 'is_file'));
        }

        return ['path' => $path, 'size' => $disk->size($path), 'files' => count($temps)];
    }

    /** @return iterable<string, array{0: list<string>, 1: LazyCollection}> file => [columns, rows] */
    private function sources(Workspace $workspace): iterable
    {
        yield 'members' => [['id', 'first_name', 'last_name', 'email', 'phone', 'role', 'status', 'created_at'], $this->rows->members($workspace->id)];

        foreach (self::OWN as $file => $table) {
            yield $file => [$this->columns($table), $this->rows->ownRows($table, $workspace->id)];
        }

        foreach (self::CHILDREN as $file => [$table, $foreignKey, $parent]) {
            yield $file => [$this->columns($table), $this->rows->childRows($table, $foreignKey, $parent, $workspace->id)];
        }
    }

    /** @return list<string> $table's columns, without its secrets */
    private function columns(string $table): array
    {
        return array_values(array_filter($this->rows->columns($table), fn (string $column) => ! preg_match(self::SECRET, $column)));
    }

    /** $rows as a CSV file, $columns first: its temporary path. */
    private function csv(array $columns, LazyCollection $rows): string
    {
        $temp   = tempnam(sys_get_temp_dir(), 'izs-export-');
        $handle = fopen($temp, 'w');

        fputcsv($handle, $columns, escape: '');

        foreach ($rows as $row) {
            $row = (array) $row;
            fputcsv($handle, array_map(fn (string $column) => $row[$column] ?? null, $columns), escape: '');
        }

        fclose($handle);

        return $temp;
    }

    private function readme(Workspace $workspace): string
    {
        return implode("\n", [
            "izSuite data export of {$workspace->name} ({$workspace->businessId()})",
            'Made on '.now()->format('j F Y \a\t H:i').' UTC.',
            '',
            'Each CSV file holds one kind of record; its first row names the columns.',
            'Dates and times are in UTC. Amounts are as kept, without currency symbols.',
            'Files kept with the business - its logo, attachments - are listed in files.csv but not included.',
            'Passwords, keys and links are never exported.',
            '',
        ]);
    }
}
