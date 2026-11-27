<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\FileCollection;
use App\Models\User\EcommerceOrder;
use App\Models\User\EcommerceOrderDelivery;
use App\Models\User\StorefrontCustomer;
use App\Repositories\User\EcommerceOrderDeliveryRepository;
use App\Repositories\User\EcommerceOrderDownloadRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Repositories\User\EcommerceSettingRepository;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Repositories\User\WorkspaceFileRepository;
use App\Services\Api\Sales\RefusesConflicts;
use App\Services\Documents\DocumentFormat;
use App\Services\Files\FileStorage;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\BusinessCalendar;
use Illuminate\Support\Facades\DB;

/**
 * A delivered digital product's files downloaded by its customer (Doc 19
 * section 19.28 - Phase G3b): from the order's own page, by its link's key,
 * or signed in, from their account's - as the product was delivered: until
 * its last day, each file so many times, and signed in to their account
 * where Settings → eCommerce asks it. Each download is recorded - which file,
 * the account, from where - and answered with a link to the file itself
 * that works for a few minutes. Nothing goes in the business's Activity Log:
 * the downloads are their own record, on the order's page.
 */
class StorefrontOrderDownloads
{
    use RefusesConflicts;

    public function __construct(
        protected StorefrontCatalogRepository $catalog,
        protected EcommerceOrderRepository $orders,
        protected EcommerceOrderDeliveryRepository $deliveries,
        protected EcommerceOrderDownloadRepository $downloads,
        protected EcommerceSettingRepository $settings,
        protected WorkspaceFileRepository $files,
        protected FileStorage $storage,
        protected BusinessCalendar $calendar,
    ) {}

    /**
     * By the order's own link's key, without signing in - refused when the
     * settings ask for the customer's account. $from: the request's ip and agent.
     *
     * @return array{url: string, left: ?int}
     */
    public function byKey(string $code, string $key, int $deliveryId, int $fileId, array $from): array
    {
        $storefront = $this->catalog->findStore($code) ?? abort(404, 'There is no such store.');
        $order      = $this->orders->forCustomer($storefront, hash('sha256', $key)) ?? abort(404, 'There is no such order. Check the link you were sent.');

        if ($this->settings->forWorkspace($order->workspace)->downloads_need_account) {
            abort(403, 'Sign in to your account to download it - with the e-mail you ordered with.');
        }

        return $this->download($order, $deliveryId, $fileId, null, $from);
    }

    /**
     * Signed in, from one of $customer's orders.
     *
     * @return array{url: string, left: ?int}
     */
    public function ofAccount(StorefrontCustomer $customer, int $orderId, int $deliveryId, int $fileId, array $from): array
    {
        $order = $this->orders->findOfAccount($customer, $orderId) ?? abort(404, 'There is no such order in your account.');

        return $this->download($order, $deliveryId, $fileId, $customer, $from);
    }

    /** @return array{url: string, left: ?int} */
    private function download(EcommerceOrder $order, int $deliveryId, int $fileId, ?StorefrontCustomer $customer, array $from): array
    {
        ActivityLogService::skipForThisRequest();

        return DB::transaction(function () use ($order, $deliveryId, $fileId, $customer, $from) {
            $delivery = $this->deliveries->lockFor($order, $deliveryId);
            $line     = $delivery ? $order->lines->firstWhere('id', $delivery->ecommerce_order_line_id) : null;

            if (! $delivery || ! $line || $delivery->method !== EcommerceOrderDelivery::DOWNLOAD) {
                abort(404, 'There is no such download.');
            }

            if ($delivery->status !== EcommerceOrderDelivery::DELIVERED) {
                $this->refuse("{$line->title} is not available to download.");
            }

            if ($delivery->expires_at?->isPast()) {
                $this->refuse("Your downloads of {$line->title} ended on {$this->lastDay($order, $delivery)}: ask the store for more.");
            }

            $file = $line->item ? $this->files->findInCollection($line->item, FileCollection::DigitalFiles, $fileId) : null;

            if (! $file) {
                abort(404, "{$line->title} has no such file.");
            }

            $used = $this->downloads->countOf($delivery, $file->id);

            if ($delivery->download_limit !== null && $used >= $delivery->download_limit) {
                $this->refuse("You have downloaded {$file->name} ".($used === 1 ? 'once' : "{$used} times").', as often as it allows: ask the store for more.');
            }

            $this->downloads->record($delivery, $file, $customer, $from);

            return ['url' => $this->storage->downloadLink($file), 'left' => $delivery->download_limit === null ? null : $delivery->download_limit - $used - 1];
        });
    }

    /** The last day $delivery's downloads worked, where the business is, as it writes dates. */
    private function lastDay(EcommerceOrder $order, EcommerceOrderDelivery $delivery): string
    {
        $workspace = $order->workspace;

        return (new DocumentFormat($workspace->date_format ?? 'd M Y', $workspace->number_format ?? 'comma_dot'))
            ->date($delivery->expires_at->copy()->setTimezone($this->calendar->timezone($workspace))->toDateString());
    }
}
