<?php

namespace App\Services\Api\BusinessSetup;

use App\Models\User\PaymentTerm;
use App\Models\User\Workspace;
use App\Repositories\User\PaymentTermRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Settings > Payment Terms (Doc 11 section 14): the business's one list of
 * named terms, which the sales, purchase and expense defaults, contacts and
 * documents pick from - any other number of days stays possible, as Custom.
 *
 * They keep the days rather than the term, so changes reach new transactions
 * only. A term whose days any of them holds is in use: it is switched off
 * rather than deleted, and its days stay as they are - a new term takes
 * other days.
 */
class PaymentTermService
{
    use RefusesConflicts;

    public function __construct(
        protected PaymentTermRepository $paymentTermRepository,
    ) {}

    /** The list, each term saying whether it is in use (`in_use`). */
    public function list(Workspace $workspace): Collection
    {
        $used = $this->paymentTermRepository->daysInUse($workspace);

        return $this->paymentTermRepository->getFor($workspace)->each(function (PaymentTerm $term) use ($used) {
            $term->in_use = in_array($term->days, $used, true);
        });
    }

    /** $data is validated (see SavePaymentTermRequest). */
    public function create(Workspace $workspace, array $data): PaymentTerm
    {
        $term = $this->paymentTermRepository->create($workspace, $data);

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer entry for the same request.
        ActivityLogService::record('created', "Added payment term {$this->quoted($term)}", $term, ['source' => 'api']);

        return $this->marked($workspace, $term);
    }

    public function update(Workspace $workspace, int $id, array $data): PaymentTerm
    {
        $term = $this->find($workspace, $id);

        if (isset($data['days']) && (int) $data['days'] !== $term->days && $this->inUse($workspace, $term)) {
            throw ValidationException::withMessages(['days' => 'This term is in use, so its days stay as they are. Add a new term for other days.']);
        }

        $this->paymentTermRepository->update($term, $data);

        ActivityLogService::record('updated', "Updated payment term {$this->quoted($term)}", $term, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($term->getChanges(), ['updated_at' => true])),
        ]);

        return $this->marked($workspace, $term);
    }

    /** Only a term nothing holds can be deleted (section 14): one in use is switched off instead. */
    public function delete(Workspace $workspace, int $id): void
    {
        $term = $this->find($workspace, $id);

        if ($this->inUse($workspace, $term)) {
            $this->refuse('This term is in use, so it cannot be deleted. Switch it off instead.');
        }

        $this->paymentTermRepository->delete($term);

        ActivityLogService::record('deleted', "Deleted payment term {$this->quoted($term)}", $term, ['source' => 'api']);
    }

    private function inUse(Workspace $workspace, PaymentTerm $term): bool
    {
        return in_array($term->days, $this->paymentTermRepository->daysInUse($workspace), true);
    }

    private function marked(Workspace $workspace, PaymentTerm $term): PaymentTerm
    {
        $term->in_use = $this->inUse($workspace, $term);

        return $term;
    }

    /** Another business's term answers like one that does not exist. */
    private function find(Workspace $workspace, int $id): PaymentTerm
    {
        return $this->paymentTermRepository->findFor($workspace, $id) ?? abort(404, 'Payment term not found.');
    }

    private function quoted(PaymentTerm $term): string
    {
        return '"'.Str::limit($term->name, 50).'"';
    }
}
