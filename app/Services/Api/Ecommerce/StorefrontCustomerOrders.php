<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\EcommerceOrder;
use App\Models\User\StorefrontCustomer;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommerceSettingRepository;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

/**
 * A storefront customer's orders on their account (Doc 19 section 19.16 -
 * Phase E2): those placed signed in, and their e-mail's guest orders once
 * the account is verified - on any of the business's storefronts - each
 * with its three statuses (its tracking), and an order's own page. Its
 * invoice and receipts join it once Phase F issues them.
 */
class StorefrontCustomerOrders
{
    public const PER_PAGE = 20;

    public function __construct(
        protected EcommerceOrderRepository $orders,
        protected EcommerceSettingRepository $settings,
    ) {}

    public function page(StorefrontCustomer $customer): LengthAwarePaginator
    {
        return $this->orders->ofAccount($customer, self::PER_PAGE);
    }

    public function show(StorefrontCustomer $customer, int $id): EcommerceOrder
    {
        $order = $this->orders->findOfAccount($customer, $id) ?? abort(404, 'There is no such order in your account.');

        // Phase G3b: whether its downloads need the customer signed in - as they are here.
        $order->downloadsNeedAccount = (bool) $this->settings->forWorkspace($order->workspace)->downloads_need_account;

        return $order;
    }
}
