<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * The record a business route takes by its {id}, where that record is at a
 * location (Doc 12 sections 20 and 21): a member who may not use its
 * location is refused the route - the record's PDF, e-mail, link and
 * attachments with it (LocationAccess).
 *
 * Found by the start of the route's name, the first that fits. A route named
 * in another family but taking its own record's id is listed apart.
 */
class RouteRecords
{
    public const SALES_DOCUMENT = 'sales_document';

    public const SALES_PAYMENT = 'sales_payment';

    public const PURCHASE = 'purchase';

    public const SUPPLIER_PAYMENT = 'supplier_payment';

    public const EXPENSE = 'expense';

    public const EXPENSE_PAYMENT = 'expense_payment';

    public const PAYMENT_ACCOUNT = 'payment_account';

    public const ACCOUNT_TRANSFER = 'account_transfer';

    /** A purchase's or an expense's new payment: the purchase's or the expense's id. */
    private const ROUTES = [
        'api.purchases.payments.store' => self::PURCHASE,
        'api.expenses.payments.store'  => self::EXPENSE,
    ];

    private const PREFIXES = [
        'api.sales.payments.'     => self::SALES_PAYMENT,
        'api.sales.'              => self::SALES_DOCUMENT,
        'api.purchases.payments.' => self::SUPPLIER_PAYMENT,
        'api.purchases.'          => self::PURCHASE,
        'api.expenses.payments.'  => self::EXPENSE_PAYMENT,
        'api.expenses.'           => self::EXPENSE,
        'api.payment-accounts.'   => self::PAYMENT_ACCOUNT,
        'api.account-transfers.'  => self::ACCOUNT_TRANSFER,
    ];

    /** The kind of record route $name takes by its {id} - null for none at a location. */
    public static function kindOf(string $name): ?string
    {
        if (isset(self::ROUTES[$name])) {
            return self::ROUTES[$name];
        }

        foreach (self::PREFIXES as $prefix => $kind) {
            if (str_starts_with($name, $prefix)) {
                return $kind;
            }
        }

        return null;
    }

    /**
     * The request's record, as [kind, id] - null when its route takes none
     * at a location.
     *
     * @return array{0: string, 1: int}|null
     */
    public static function for(Request $request): ?array
    {
        $route = $request->route();
        $id    = $route?->parameter('id');
        $kind  = $route?->getName() ? self::kindOf($route->getName()) : null;

        return $kind !== null && is_numeric($id) ? [$kind, (int) $id] : null;
    }
}
