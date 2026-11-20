<?php

namespace App\Services\Api\Pos;

use App\Models\User\User;
use App\Models\User\Workspace;
use App\Repositories\User\PosSettingRepository;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Validation\ValidationException;

/**
 * The discounts and price overrides a POS sale gives (Doc 18 sections 18.12
 * and 18.13 - Phase C3), as the user may give them: a discount - on a line or
 * on the whole order, by percentage or amount - with Apply Discounts; a price
 * typed over a line's with Override Prices, and why.
 *
 * Settings → Point of Sale's discount limit caps each of them, as a
 * percentage of what it is taken off - an override counting by how far below
 * the price it replaced it goes. Beyond it, the sale needs Discount Beyond
 * the Limit and a reason: a manager's, who is recorded as allowing it.
 * Phase I2: a manager approves at the register what the cashier may not
 * do alone - a price typed over, a discount beyond the limit - by their PIN.
 * Without a limit, nothing is beyond it.
 */
class PosDiscounts
{
    public function __construct(
        protected PosSettingRepository $settings,
        protected WorkspaceAccess $access,
        protected PosApprovals $approvals,
    ) {}

    /**
     * $lines as PosCounter::lines() gives them, $priced as PosPricing::priced()
     * did; $discount the order's. Refuses what the user may not give - and,
     * while $completing, an override without its reason. Whether any of it
     * goes beyond the limit, and the limit.
     *
     * @return array{beyond: bool, limit: ?string, override_approver: ?User}
     */
    public function check(Workspace $workspace, array $lines, array $priced, ?array $discount, bool $completing, array $approvals = []): array
    {
        $limit  = $this->settings->forWorkspace($workspace)->discount_limit;
        $limit  = $limit === null ? null : (string) $limit;
        $beyond = false;
        $sold   = '0.00';
        $overrider = null;

        foreach ($lines as $index => $line) {
            $row   = $priced['rows'][$index];
            $price = $priced['prices'][$index];
            $sold  = bcadd($sold, (string) $row['amount'], 2);

            if ($line['discount_value'] !== null) {
                $this->may('pos.selling.discount', "lines.{$index}.discount_value", 'You may not give discounts.');

                $percent = $line['discount_type'] === 'percent' ? $line['discount_value'] : $this->share((string) $row['discount_amount'], bcadd((string) $row['amount'], (string) $row['discount_amount'], 2));
                $beyond  = $this->over($percent, $limit) || $beyond;
            }

            if ($line['price'] !== null) {
                $overrider ??= $this->approver($workspace, 'override', 'pos.selling.override', $approvals);
                $this->may('pos.selling.override', "lines.{$index}.price", 'You may not change prices.', $overrider);

                if ($completing && $line['override_reason'] === null) {
                    throw ValidationException::withMessages(["lines.{$index}.override_reason" => "Say why {$line['item']->name}'s price is changed."]);
                }

                if ($price['standard'] !== null && bccomp($line['price'], $price['standard'], 2) < 0) {
                    $beyond = $this->over($this->share(bcsub($price['standard'], $line['price'], 2), $price['standard']), $limit) || $beyond;
                }
            }
        }

        if ($discount !== null) {
            $this->may('pos.selling.discount', 'discount_value', 'You may not give discounts.');

            $percent = $discount['type'] === 'percent' ? $discount['value'] : $this->share((string) $priced['totals']['discount_total'], $sold);
            $beyond  = $this->over($percent, $limit) || $beyond;
        }

        return ['beyond' => $beyond, 'limit' => $limit, 'override_approver' => $overrider];
    }

    /**
     * A sale whose discounts go beyond the limit: allowed to one with
     * Discount Beyond the Limit - or approved at the register by one who has
     * it (Phase I2) - saying why. The reason, trimmed, and who allowed it.
     *
     * @return array{reason: string, approver_id: int, approver: ?User}
     */
    public function allowBeyond(Workspace $workspace, ?string $reason, string $limit, array $approvals = []): array
    {
        $approver = $this->approver($workspace, 'beyond', 'pos.selling.beyond', $approvals);

        if (! $this->access->can('pos.selling.beyond') && ! $approver) {
            throw ValidationException::withMessages(['discount_reason' => "This goes beyond the {$this->percent($limit)}% discount limit: someone with Discount Beyond the Limit gives it - or approves it at the register."]);
        }

        $reason = trim((string) $reason);

        if ($reason === '') {
            throw ValidationException::withMessages(['discount_reason' => "Say why the discount goes beyond the {$this->percent($limit)}% limit."]);
        }

        return ['reason' => $reason, 'approver_id' => (int) ($approver?->id ?? $this->access->user()->id), 'approver' => $approver];
    }

    /**
     * Whether a discount of $percent is beyond $limit: never without a limit.
     * A percentage given counts as given, not as its amount once rounded to
     * the cent - so 10% off is within a 10% limit.
     */
    private function over(string $percent, ?string $limit): bool
    {
        return $limit !== null && bccomp($percent, $limit, 10) > 0;
    }

    /** What percentage $part is of $of - nil of nothing. */
    private function share(string $part, string $of): string
    {
        return bccomp($of, '0', 2) <= 0 ? '0' : bcdiv(bcmul($part, '100', 10), $of, 10);
    }

    private function may(string $permission, string $field, string $message, ?User $approver = null): void
    {
        if (! $this->access->can($permission) && ! $approver) {
            throw ValidationException::withMessages([$field => $message]);
        }
    }

    /** A manager approving $action at the register for a user who may not do it alone (Phase I2) - none when they may, or no good approval came. */
    private function approver(Workspace $workspace, string $action, string $permission, array $approvals): ?User
    {
        return $this->access->can($permission) ? null : $this->approvals->approverFor($workspace, $action, $approvals[$action] ?? null);
    }
    /** "10" for 10.00, "12.5" for 12.50. */
    private function percent(string $limit): string
    {
        return rtrim(rtrim($limit, '0'), '.');
    }
}
