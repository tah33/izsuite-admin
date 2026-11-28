<?php

namespace App\Services\Api\Ecommerce;

use App\Jobs\SendStoreQuoteRequestEmail;
use App\Mail\StoreQuoteRequestMail;
use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\EcommerceQuoteRequestItem;
use App\Models\User\Storefront;
use App\Models\User\StorefrontCustomer;
use App\Repositories\User\EcommerceQuoteRequestRepository;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Services\Documents\DocumentFormat;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Workspaces\WorkspaceSequence;
use App\Support\Countries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\ValidationException;

/**
 * A customer's request for a quotation on a storefront (Doc 19 section 19.30
 * - Phase H4a), a guest's or a signed-in customer's - the account's name and
 * e-mail then:
 *
 * - each product one the store shows, sold by quotation - its options among
 *   those it shows - kept as it is now, with how many;
 * - the delivery location, the date wanted (today or later, where the
 *   business is) and a message;
 * - numbered QR-00001 from the business's own sequence, the customer
 *   e-mailed it arrived (through the queue), and in the Activity Log, put
 *   down to the customer.
 *
 * It is the business's sales enquiry, never an order, a sale or a
 * receivable. What it may also become - a CRM lead, a draft quotation - is
 * Phase H4b's.
 */
class StorefrontQuoteRequests
{
    public function __construct(
        protected StorefrontCatalogService $stores,
        protected StorefrontCatalogRepository $catalog,
        protected EcommerceQuoteRequestRepository $requests,
        protected WorkspaceSequence $sequence,
    ) {}

    /** $data as SubmitStoreQuoteRequestRequest takes it; $account: the customer signed in, if they are. */
    public function submit(string $code, array $data, ?StorefrontCustomer $account = null): EcommerceQuoteRequest
    {
        ['storefront' => $storefront, 'today' => $today] = $this->stores->openStore($code);

        if (($data['required_on'] ?? null) !== null && $data['required_on'] < $today) {
            throw ValidationException::withMessages(['required_on' => 'Choose today or a later day.']);
        }

        $items = array_map(fn (array $item, int $at) => $this->item($storefront, $item, $today, $at), $data['items'], array_keys($data['items']));

        $request = DB::transaction(fn () => $this->requests->create($storefront, [
            'storefront_customer_id' => $account?->id,
            'number'                 => $this->number($storefront),
            'customer_name'          => $account ? $account->name : trim($data['name']),
            'customer_email'         => $account ? $account->email : mb_strtolower(trim($data['email'])),
            'customer_phone'         => trim((string) ($data['phone'] ?? '')) ?: $account?->phone,
            'company'                => trim((string) ($data['company'] ?? '')) ?: null,
            'delivery_country'       => mb_strtoupper($data['delivery']['country']),
            'delivery_city'          => trim((string) ($data['delivery']['city'] ?? '')) ?: null,
            'delivery_address'       => trim((string) ($data['delivery']['address'] ?? '')) ?: null,
            'required_on'            => $data['required_on'] ?? null,
            'message'                => trim((string) ($data['message'] ?? '')) ?: null,
        ], $items));

        SendStoreQuoteRequestEmail::dispatch($request->id)->afterCommit();
        ActivityLogService::recordCustomer($storefront->workspace, 'created', "{$request->customer_name} asked for a quotation on {$storefront->name}: {$request->number}", $request);

        return $request;
    }

    /** Sends the request $id's e-mail to its customer, saying it arrived - the queue job's work. */
    public function deliver(int $id): void
    {
        $request = $this->requests->findForEmail($id);

        if (! $request) {
            return;
        }

        $storefront = $request->storefront;
        $workspace  = $storefront->workspace;
        $format     = new DocumentFormat($workspace->date_format ?? 'd M Y', $workspace->number_format ?? 'comma_dot');

        Mail::to($request->customer_email)->send(new StoreQuoteRequestMail(
            "We have your request for a quotation, {$request->number}",
            $storefront->name,
            $storefront->support_email,
            [
                'name'        => $request->customer_name,
                'number'      => $request->number,
                'items'       => $request->items->map(fn (EcommerceQuoteRequestItem $item) => ['title' => $item->title, 'quantity' => rtrim(rtrim((string) $item->quantity, '0'), '.')])->all(),
                'delivery'    => implode(', ', array_filter([$request->delivery_address, $request->delivery_city, Countries::name($request->delivery_country)])),
                'required_on' => $request->required_on ? $format->date($request->required_on->toDateString()) : null,
                'message'     => $request->message,
            ],
        ));
    }

    /** The product $item asks about - one the store shows, sold by quotation, its options among those it shows - as it is now. */
    private function item(Storefront $storefront, array $item, string $today, int $at): array
    {
        $field   = "items.{$at}.product";
        $product = $this->catalog->product($storefront, (string) $item['product'], $today)
            ?? throw ValidationException::withMessages([$field => 'This product is not sold in this store.']);

        if ($product->sales_method !== CatalogProduct::QUOTATION) {
            throw ValidationException::withMessages([$field => $product->sales_method === CatalogProduct::FIXED_PRICE ? 'Add it to your cart instead.' : 'Ask the store about this product.']);
        }

        $variant = null;

        if ($product->variants->isNotEmpty()) {
            $variant = $product->variants->firstWhere('id', (int) ($item['variant_id'] ?? 0))
                ?? throw ValidationException::withMessages(["items.{$at}.variant_id" => 'Choose its options.']);
        } elseif (($item['variant_id'] ?? null) !== null) {
            throw ValidationException::withMessages(["items.{$at}.variant_id" => 'This product has no options to choose.']);
        }

        return [
            'catalog_product_id'         => $product->id,
            'catalog_product_variant_id' => $variant?->id,
            'item_id'                    => ($variant?->item ?? $product->item)?->id,
            'title'                      => mb_substr($product->title.($variant ? ' ('.implode(', ', (array) $variant->option_values).')' : ''), 0, 255),
            'quantity'                   => (string) $item['quantity'],
        ];
    }

    /** The next number of the business's requests, QR-00001 on - one taken already skipped. */
    private function number(Storefront $storefront): string
    {
        do {
            $number = EcommerceQuoteRequest::NUMBER_PREFIX.sprintf('%05d', $this->sequence->next($storefront->workspace_id, EcommerceQuoteRequest::NUMBER_SEQUENCE));
        } while ($this->requests->numberTaken($storefront->workspace, $number));

        return $number;
    }
}
