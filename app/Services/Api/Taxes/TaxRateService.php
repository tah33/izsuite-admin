<?php

namespace App\Services\Api\Taxes;

use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\PurchaseRepository;
use App\Repositories\User\SalesDocumentRepository;
use App\Repositories\User\TaxRateRepository;
use App\Services\Shared\ActivityLogService;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Settings > Taxes > Tax Rates (Doc 11 section 10): adding, changing and
 * removing a business's rates.
 *
 * The business's default sales and purchase tax must stay something new
 * documents can use: it cannot be switched off, stop applying to its side, or
 * be deleted until another default - or none - is chosen. A rate items start
 * with, or one on a sales document or a purchase, is deactivated instead of
 * deleted ("used configuration records are deactivated", Doc 11 section 25).
 *
 * A rate on a sales document or a purchase keeps its percentage and
 * treatment: a new rate replaces it (Doc 11 section 10, "a used rate is
 * deactivated and replaced with a new effective rate"), so a draft's taxes
 * never change under it.
 */
class TaxRateService
{
    public function __construct(
        protected TaxRateRepository $taxRateRepository,
        protected SalesDocumentRepository $salesDocumentRepository,
        protected PurchaseRepository $purchaseRepository,
    ) {}

    public function list(Workspace $workspace): Collection
    {
        return $this->taxRateRepository->getFor($workspace);
    }

    /** $data is validated (see SaveTaxRateRequest). */
    public function create(Workspace $workspace, array $data): TaxRate
    {
        $rate = $this->taxRateRepository->create($workspace, $this->columns($data));

        // Each write is logged here, by name, so LogApiWriteActivity does not
        // add its own vaguer "POST api/v1/tax-rates" entry for the same request.
        ActivityLogService::record('created', "Added tax rate {$this->quoted($rate)}", $rate, ['source' => 'api']);

        return $rate;
    }

    public function update(Workspace $workspace, int $id, array $data): TaxRate
    {
        $rate    = $this->findOwned($workspace, $id);
        $columns = $this->columns($data);

        foreach ($rate->defaultFor() as $side) {
            if (array_key_exists('is_active', $columns) && ! $columns['is_active']) {
                $this->refuseForDefault($rate, $side, 'switched off');
            }

            if (! $columns["for_{$side}"]) {
                throw ValidationException::withMessages([
                    'applies_to' => "This is your default {$this->sideNoun($side)} tax, so it must apply to {$side}. Choose another default under Tax Options first.",
                ]);
            }
        }

        $changesCharge = bccomp((string) $columns['rate'], (string) $rate->rate, 4) !== 0 || $columns['treatment'] !== $rate->treatment->value;

        if ($changesCharge && $this->salesDocumentRepository->usesTaxRate($rate)) {
            throw ValidationException::withMessages([
                'rate' => 'This rate is on sales documents, so its rate and treatment stay as they are. Add a new rate and switch this one off instead.',
            ]);
        }

        if ($changesCharge && $this->purchaseRepository->usesTaxRate($rate)) {
            throw ValidationException::withMessages([
                'rate' => 'This rate is on purchases, so its rate and treatment stay as they are. Add a new rate and switch this one off instead.',
            ]);
        }

        $this->taxRateRepository->update($rate, $columns);

        ActivityLogService::record('updated', "Updated tax rate {$this->quoted($rate)}", $rate, [
            'source' => 'api',
            'fields' => array_keys(array_diff_key($rate->getChanges(), ['updated_at' => true])),
        ]);

        return $rate;
    }

    public function delete(Workspace $workspace, int $id): void
    {
        $rate = $this->findOwned($workspace, $id);

        foreach ($rate->defaultFor() as $side) {
            $this->refuseForDefault($rate, $side, 'deleted');
        }

        if ($this->salesDocumentRepository->usesTaxRate($rate)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This tax is on sales documents, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        if ($this->purchaseRepository->usesTaxRate($rate)) {
            throw new HttpResponseException(response()->json([
                'message' => 'This tax is on purchases, so it cannot be deleted. Deactivate it instead.',
            ], 409));
        }

        $items = $this->taxRateRepository->itemCount($rate);

        if ($items > 0) {
            throw new HttpResponseException(response()->json([
                'message' => ($items === 1 ? '1 item starts' : "{$items} items start")." with this tax. Deactivate it instead, or change those items first.",
            ], 409));
        }

        $this->taxRateRepository->delete($rate);

        ActivityLogService::record('deleted', "Deleted tax rate {$this->quoted($rate)}", $rate, ['source' => 'api']);
    }

    /**
     * The request's fields as the table holds them: what it applies to as one
     * column per side, and a recoverability only where it means something.
     */
    private function columns(array $data): array
    {
        $sides = $data['applies_to'] ?? [];

        foreach (TaxRate::SIDES as $side) {
            $data["for_{$side}"] = in_array($side, $sides, true);
        }

        if (! $data['for_purchases'] && ! $data['for_expenses']) {
            $data['recoverability'] = null;
        }

        unset($data['applies_to']);

        return $data;
    }

    private function refuseForDefault(TaxRate $rate, string $side, string $done): never
    {
        throw new HttpResponseException(response()->json([
            'message' => "{$this->quoted($rate)} is your default {$this->sideNoun($side)} tax, so it cannot be {$done}. Choose another default - or none - under Tax Options first.",
        ], 409));
    }

    private function sideNoun(string $side): string
    {
        return $side === TaxRate::SALES ? 'sales' : 'purchase';
    }

    /**
     * A rate of another business - the owner's own other one, or someone
     * else's - answers exactly like one that does not exist.
     */
    private function findOwned(Workspace $workspace, int $id): TaxRate
    {
        return $this->taxRateRepository->findFor($workspace, $id)
            ?? abort(404, 'Tax rate not found.');
    }

    /** The name, in quotes and cut down, so the log entry fits its 255-character column. */
    private function quoted(TaxRate $rate): string
    {
        return '"'.Str::limit($rate->name, 100).'"';
    }
}
