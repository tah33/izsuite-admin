<?php

namespace App\Services\Shared;

use App\Models\Shared\ActivityLog;
use App\Models\User\Workspace;
use App\Repositories\Shared\RecordActivityRepository;
use Illuminate\Support\Collection;

/**
 * A record's activity timeline on its page (Doc 14 section 14.14 - Phase D):
 * what was done to a sales document, purchase or expense and its money, by
 * whom and when, newest first. It is read from the Activity Log, whose
 * entries nobody edits or deletes. An entry made by no user is izSuite's
 * own.
 *
 * Anyone who may view the record sees its timeline. Amounts in it are for
 * those who may also see the app's values (Doc 12 section 14). For anyone
 * else an entry's text loses its amounts ("Recorded payment RCT-00001 for
 * invoice INV-00001"), and its previous and new values are left out.
 */
class RecordActivityService
{
    /** The newest this many: more than any one record gathers in practice. */
    public const SHOWN = 100;

    public function __construct(
        protected DiscussedRecords $records,
        protected RecordActivityRepository $recordActivityRepository,
        protected WorkspaceAccess $access,
    ) {}

    /** Each entry with `shown_description` and `shown_changes` set, as the role may see them. */
    public function timeline(Workspace $workspace, string $family, ?string $kind, int $id): Collection
    {
        $record = $this->records->find($workspace, $family, $kind, $id);
        $values = $this->access->seesValues($family);

        $entries = match ($family) {
            'sales'     => $this->recordActivityRepository->forSalesDocument($record->model, self::SHOWN),
            'purchases' => $this->recordActivityRepository->forPurchase($record->model, self::SHOWN),
            'expenses'  => $this->recordActivityRepository->forExpense($record->model, self::SHOWN),
        };

        return $entries->each(function (ActivityLog $entry) use ($values) {
            $entry->setAttribute('shown_description', $values ? $entry->description : self::withoutAmounts($entry->description));
            $entry->setAttribute('shown_changes', $values ? ($entry->properties['changes'] ?? []) : []);
        });
    }

    /**
     * $text without the amounts written into it: "Recorded payment RCT-00001
     * of USD 100.00 for invoice INV-00001" reads "Recorded payment RCT-00001
     * for invoice INV-00001", and "Refunded USD 10.00 from credit note
     * CN-00001" reads "Refunded from credit note CN-00001".
     */
    public static function withoutAmounts(string $text): string
    {
        $amount = '[A-Z]{3} -?[\d,]+(?:\.\d+)?';

        return trim(preg_replace(["/ of {$amount}/", "/{$amount}\\s?/"], '', $text));
    }
}
