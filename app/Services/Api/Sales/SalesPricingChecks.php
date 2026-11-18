<?php

namespace App\Services\Api\Sales;

use App\Enums\Edition;
use App\Models\User\SalesDocument;
use App\Models\User\Workspace;
use App\Repositories\User\PriceListRepository;
use App\Repositories\User\WorkspaceRoleRepository;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Apps\WorkspaceApps;
use App\Support\Decimal;
use Illuminate\Validation\ValidationException;

/**
 * What a sales form may price and discount (Doc 16 section 16.20), with the
 * full Sales app. An item's line costs its item's price, or what its price
 * list's rule makes it; another price needs "Change Prices by Hand" - unless
 * the line already had it, or came with it from the document it was made from.
 * A discount - a line's or the whole document's - past the largest the user's
 * role gives needs "Discount Beyond the Limit" and a reason, which the
 * document keeps (discount_reason). The owner and full-access roles have no
 * limit.
 */
class SalesPricingChecks
{
    public const OVERRIDE = 'sales.pricing.override';

    public const BEYOND = 'sales.pricing.beyond';

    public function __construct(
        protected PriceListRepository $priceLists,
        protected PriceListPricing $pricing,
        protected WorkspaceRoleRepository $roles,
        protected WorkspaceAccess $access,
    ) {}

    /** $header: the document's currency, exchange_rate, discount_type, discount_value and discount_reason; $rows: its lines as SalesLines made them. */
    public function check(Workspace $workspace, array $header, array $rows, ?SalesDocument $document): void
    {
        if (! WorkspaceApps::for($workspace)->has('sales', Edition::Full)) {
            return;
        }

        $this->checkPrices($workspace, $header, $rows, $document);
        $this->checkDiscounts($workspace, $header, $rows);
    }

    /** The largest discount the signed-in user may give, as a percentage - null for no limit. */
    public function limit(Workspace $workspace): ?string
    {
        $role = $this->access->role();

        return $role === null ? null : $this->roles->findFor($workspace, $role)?->max_discount;
    }

    private function checkPrices(Workspace $workspace, array $header, array $rows, ?SalesDocument $document): void
    {
        if ($this->access->can(self::OVERRIDE)) {
            return;
        }

        $items = $this->priceLists->itemsFor($workspace, array_values(array_filter(array_column($rows, 'item_id'))))->keyBy('id');
        $rules = $this->priceLists->rulesFor(array_values(array_filter(array_column($rows, 'price_list_rule_id'))))->keyBy('id');
        $had   = $document?->lines->map(fn ($line) => "{$line->item_id}:".Decimal::round((string) $line->unit_price))->all() ?? [];
        $rate  = $workspace->base_currency === ($header['currency'] ?? $workspace->base_currency) ? '1' : (string) ($header['exchange_rate'] ?? '1');

        foreach (array_values($rows) as $index => $row) {
            $item = $items->get($row['item_id'] ?? 0);

            if (! $item || ! empty($row['deposit_invoice_id']) || ! empty($row['source_line_id']) || in_array("{$item->id}:".Decimal::round((string) $row['unit_price']), $had, true)) {
                continue;
            }

            $rule     = $rules->get($row['price_list_rule_id'] ?? 0);
            $expected = $rule ? $this->pricing->priceBy($rule, $item, $rate) : ($item->sale_price !== null ? Decimal::round(bcdiv((string) $item->sale_price, $rate, 10)) : null);

            if ($expected !== null && bccomp(Decimal::round((string) $row['unit_price']), $expected, 2) !== 0) {
                throw ValidationException::withMessages(["lines.{$index}.unit_price" => "Only someone who may change prices by hand sets another price than {$expected}."]);
            }
        }
    }

    private function checkDiscounts(Workspace $workspace, array $header, array $rows): void
    {
        $limit = $this->limit($workspace);

        if ($limit === null || ($this->access->can(self::BEYOND) && filled($header['discount_reason'] ?? null))) {
            return;
        }

        $largest = '0';
        $sold    = '0';

        foreach ($rows as $row) {
            $gross = bcmul((string) $row['quantity'], (string) $row['unit_price'], 10);
            $sold  = bcadd($sold, $gross, 10);

            $largest = Decimal::max($largest, $this->percent($row['discount_type'] ?? null, $row['discount_value'] ?? null, $gross));
        }

        $largest = Decimal::max($largest, $this->percent($header['discount_type'] ?? null, isset($header['discount_value']) ? (string) $header['discount_value'] : null, $sold));

        if (bccomp($largest, (string) $limit, 2) > 0) {
            throw ValidationException::withMessages(['discount_reason' => $this->access->can(self::BEYOND)
                ? "Your role gives discounts up to {$limit}%. Say why this one is larger."
                : "Your role gives discounts up to {$limit}%. A larger one needs \"Discount Beyond the Limit\"."]);
        }
    }

    /** A discount as a percentage of what it comes off. */
    private function percent(?string $type, ?string $value, string $base): string
    {
        if (! $type || Decimal::isZero($value)) {
            return '0';
        }

        if ($type === 'percent') {
            return (string) $value;
        }

        return Decimal::isZero($base) ? '0' : bcdiv(bcmul((string) $value, '100', 10), $base, 2);
    }
}
