<?php

namespace App\Services\Api\Files;

use App\Models\User\Contact;
use App\Models\User\Expense;
use App\Models\User\ExpensePayment;
use App\Models\User\Item;
use App\Models\User\Purchase;
use App\Models\User\SalesDocument;
use App\Models\User\SalesPayment;
use App\Models\User\SupplierPayment;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Repositories\User\WorkspaceFileRepository;
use App\Services\Files\FileStorage;
use App\Services\Shared\LocationAccess;
use App\Services\Shared\WorkspaceAccess;
use App\Support\RouteRecords;

/**
 * Links that download a business's files: a public file's own address, or,
 * for a private file, a link that works for a few minutes and needs no
 * sign-in.
 */
class FileLinkService
{
    /** What a file's record needs to be seen (Doc 12 section 12), by the record's class. */
    private const VIEWED_WITH = [
        Contact::class         => 'contacts.view',
        Item::class            => 'items.view',
        Purchase::class        => 'purchases.attachments.view',
        Expense::class         => 'expenses.attachments.view',
        SalesPayment::class    => 'sales.payments.view',
        SupplierPayment::class => 'purchases.payments.view',
        ExpensePayment::class  => 'expenses.payments.view',
    ];

    /** The record kinds that are at a location (see LocationAccess), by the record's class. */
    private const AT_LOCATION = [
        SalesDocument::class   => RouteRecords::SALES_DOCUMENT,
        SalesPayment::class    => RouteRecords::SALES_PAYMENT,
        Purchase::class        => RouteRecords::PURCHASE,
        SupplierPayment::class => RouteRecords::SUPPLIER_PAYMENT,
        Expense::class         => RouteRecords::EXPENSE,
        ExpensePayment::class  => RouteRecords::EXPENSE_PAYMENT,
    ];

    public function __construct(
        protected WorkspaceFileRepository $workspaceFileRepository,
        protected FileStorage $fileStorage,
        protected WorkspaceAccess $access,
        protected LocationAccess $locationAccess,
    ) {}

    /**
     * One of the business's files, if the user may see the record it is
     * attached to - a sales document's by its kind - at its location. The
     * logo and a template's signature are every member's. Another business's
     * file answers like one that does not exist.
     */
    public function find(Workspace $workspace, int $id): WorkspaceFile
    {
        $file = $this->workspaceFileRepository->findFor($workspace, $id)
            ?? abort(404, 'File not found.');

        $needs = $file->attachable_type === SalesDocument::class
            ? ($this->workspaceFileRepository->attachableOf($file)?->type->permissionGroup() ?? 'sales.invoices').'.view'
            : self::VIEWED_WITH[$file->attachable_type] ?? null;

        if ($needs !== null) {
            $this->access->authorize($needs);
        }

        if (isset(self::AT_LOCATION[$file->attachable_type])) {
            abort_unless($this->locationAccess->reaches($workspace, self::AT_LOCATION[$file->attachable_type], (int) $file->attachable_id), 403, LocationAccess::REFUSED);
        }

        return $file;
    }

    /** @return array{url: ?string, name: string} */
    public function link(WorkspaceFile $file): array
    {
        return [
            'url'  => $file->isPublic() ? $this->fileStorage->url($file) : $this->fileStorage->downloadLink($file),
            'name' => $file->name,
        ];
    }
}
