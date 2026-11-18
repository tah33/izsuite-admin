<?php

namespace App\Repositories\User;

use App\Enums\DocumentType;
use App\Enums\ExpenseStatus;
use App\Enums\PurchaseStatus;
use App\Enums\SalesDocumentStatus;
use App\Models\User\Workspace;

/**
 * What is still open in an app's records (Doc 13 section 8): drafts not yet
 * issued or recorded, and documents not yet paid - which turning the app off
 * would leave without their pages until it is on again.
 */
class AppOpenItemsRepository
{
    /** @return list<array{label: string, count: int}> the kinds with any, for $family's records */
    public function for(Workspace $workspace, string $family): array
    {
        $counts = match ($family) {
            'sales'     => [
                'Drafts not yet issued' => $workspace->salesDocuments()->where('status', SalesDocumentStatus::Draft)->count(),
                'Unpaid invoices'       => $workspace->salesDocuments()->where('type', DocumentType::Invoice)->where('status', SalesDocumentStatus::Issued)->where('balance_due', '>', 0)->count(),
            ],
            'purchases' => [
                'Draft purchases'  => $workspace->purchases()->where('status', PurchaseStatus::Draft)->count(),
                'Unpaid purchases' => $workspace->purchases()->where('status', PurchaseStatus::Recorded)->where('balance_due', '>', 0)->count(),
            ],
            'expenses'  => [
                'Draft expenses'  => $workspace->expenses()->where('status', ExpenseStatus::Draft)->count(),
                'Unpaid expenses' => $workspace->expenses()->where('status', ExpenseStatus::Recorded)->where('balance_due', '>', 0)->count(),
            ],
            default     => [],
        };

        return array_values(array_map(
            fn (string $label) => ['label' => $label, 'count' => $counts[$label]],
            array_keys(array_filter($counts)),
        ));
    }
}
