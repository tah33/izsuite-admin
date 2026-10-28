<?php

namespace App\Services\Api\Taxes;

use App\Models\User\WithholdingTax;
use App\Models\User\Workspace;
use App\Repositories\User\SalesPaymentRepository;
use App\Repositories\User\WithholdingTaxRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Settings > Taxes > Withholding Tax (Doc 11 section 12): adding, changing and
 * removing a business's withholding tax rates.
 *
 * A rate withheld on a payment cannot be deleted (section 12) and keeps its
 * percentage, like a sales tax rate on a document: a new rate replaces it.
 */
class WithholdingTaxService
{
    public function __construct(
        protected WithholdingTaxRepository $withholdingTaxRepository,
        protected SalesPaymentRepository $salesPaymentRepository,
    ) {}

    public function list(Workspace $workspace): Collection
    {
        return $this->withholdingTaxRepository->getFor($workspace);
    }

    /** $data is validated (see SaveWithholdingTaxRequest). */
    public function create(Workspace $workspace, array $data): WithholdingTax
    {
        $rate = $this->withholdingTaxRepository->create($workspace, $this->columns($data));

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer entry for the same request.
        ActivityLogService::record('created', "Added withholding tax {$this->quoted($rate)}", $rate, ['source' => 'api']);

        return $rate;
    }

    public function update(Workspace $workspace, int $id, array $data): WithholdingTax
    {
        $rate    = $this->findOwned($workspace, $id);
        $columns = $this->columns($data);

        if (bccomp((string) $columns['rate'], (string) $rate->rate, 4) !== 0 && $this->salesPaymentRepository->usesWithholdingTax($rate)) {
            throw ValidationException::withMessages([
                'rate' => 'This rate was withheld on payments, so its percentage stays as it is. Add a new rate and switch this one off instead.',
            ]);
        }

        $this->withholdingTaxRepository->update($rate, $columns);

        ActivityLogService::record('updated', "Updated withholding tax {$this->quoted($rate)}", $rate, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($rate->getChanges(), ['updated_at' => true])),
        ]);

        return $rate;
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $rate = $this->findOwned($workspace, $id);

        if ($this->salesPaymentRepository->usesWithholdingTax($rate)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This rate was withheld on payments, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        $this->withholdingTaxRepository->delete($rate);

        ActivityLogService::record('deleted', "Deleted withholding tax {$this->quoted($rate)}", $rate, ['source' => 'api']);
    }

    /** The request's fields as the table holds them: what it applies to as one column per side. */
    private function columns(array $data): array
    {
        $sides = $data['applies_to'] ?? [];

        foreach (WithholdingTax::SIDES as $side) {
            $data["for_{$side}"] = in_array($side, $sides, true);
        }

        unset($data['applies_to']);

        return $data;
    }

    /** A rate of another business answers exactly like one that does not exist. */
    private function findOwned(Workspace $workspace, int $id): WithholdingTax
    {
        return $this->withholdingTaxRepository->findFor($workspace, $id)
            ?? abort(404, 'Withholding tax not found.');
    }

    /** The name, in quotes and cut down, so the log entry fits its 255-character column. */
    private function quoted(WithholdingTax $rate): string
    {
        return '"'.Str::limit($rate->name, 100).'"';
    }
}
