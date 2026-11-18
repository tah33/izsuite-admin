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

    public const CRM_LEAD = 'crm_lead';

    public const CRM_OPPORTUNITY = 'crm_opportunity';

    // The full Sales app's own records (Doc 16), each at its own location.
    public const SALES_DELIVERY = 'sales_delivery';

    public const SALES_RETURN = 'sales_return';

    public const CUSTOMER_ADVANCE = 'customer_advance';

    /** Credit applied to an invoice: at its invoice's location. */
    public const SALES_ALLOCATION = 'sales_allocation';

    /**
     * A purchase's or an expense's new payment: the purchase's or the
     * expense's id. A sales order's new delivery or return: the order's.
     * Credit taken off an invoice: the allocation's.
     */
    private const ROUTES = [
        'api.purchases.payments.store' => self::PURCHASE,
        'api.expenses.payments.store'  => self::EXPENSE,
        'api.sales.deliveries.store'   => self::SALES_DOCUMENT,
        'api.sales.returns.store'      => self::SALES_DOCUMENT,
        'api.sales.allocations.remove' => self::SALES_ALLOCATION,
    ];

    /** null: the route's {id} is a record at no one location - a price list names its own, a customer's credit is the business's. */
    private const PREFIXES = [
        'api.sales.payments.'     => self::SALES_PAYMENT,
        'api.sales.deliveries.'   => self::SALES_DELIVERY,
        'api.sales.returns.'      => self::SALES_RETURN,
        'api.sales.advances.'     => self::CUSTOMER_ADVANCE,
        'api.sales.price-lists.'  => null,
        'api.sales.customers.'    => null,
        'api.sales.'              => self::SALES_DOCUMENT,
        'api.purchases.payments.' => self::SUPPLIER_PAYMENT,
        'api.purchases.'          => self::PURCHASE,
        'api.expenses.payments.'  => self::EXPENSE_PAYMENT,
        'api.expenses.'           => self::EXPENSE,
        'api.payment-accounts.'   => self::PAYMENT_ACCOUNT,
        'api.account-transfers.'  => self::ACCOUNT_TRANSFER,
        'api.crm.leads.'          => self::CRM_LEAD,
        'api.crm.opportunities.'  => self::CRM_OPPORTUNITY,
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
