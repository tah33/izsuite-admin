<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\FileCollection;
use App\Jobs\SendStoreQuoteRequestEmail;
use App\Mail\StoreQuoteRequestMail;
use App\Models\User\CatalogProduct;
use App\Models\User\EcommerceQuoteRequest;
use App\Models\User\EcommerceQuoteRequestItem;
use App\Models\User\EcommerceSetting;
use App\Models\User\Storefront;
use App\Models\User\StorefrontCustomer;
use App\Repositories\User\EcommerceQuoteRequestRepository;
use App\Repositories\User\EcommerceSettingRepository;
use App\Repositories\User\StorefrontCatalogRepository;
use App\Services\Documents\DocumentFormat;
use App\Services\Files\FileStorage;
use App\Services\Files\StorageUsage;
use App\Services\Shared\ActivityLogService;
use App\Services\User\Workspaces\WorkspaceSequence;
use App\Support\Countries;
use Illuminate\Http\UploadedFile;
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
 *   down to the customer;
 * - from Phase H4d, up to three files where Settings → eCommerce takes them
 *   and the business has room, kept as its attachments.
 *
 * It is the business's sales enquiry, never an order, a sale or a
 * receivable. As Settings → eCommerce chooses, it also becomes - made with it
 * - a CRM lead while CRM is on (Phase H4b, QuoteRequestLeads) or a draft
 * quotation while Sales is on (Phase H4c, QuoteRequestQuotations).
 */
class StorefrontQuoteRequests
{
    public function __construct(
        protected StorefrontCatalogService $stores,
        protected StorefrontCatalogRepository $catalog,
        protected EcommerceQuoteRequestRepository $requests,
        protected EcommerceSettingRepository $settings,
        protected WorkspaceSequence $sequence,
        protected QuoteRequestLeads $leads,
        protected QuoteRequestQuotations $quotations,
        protected FileStorage $files,
        protected StorageUsage $usage,
    ) {}

    /** $data as SubmitStoreQuoteRequestRequest takes it - its files among it; $account: the customer signed in, if they are. */
    public function submit(string $code, array $data, ?StorefrontCustomer $account = null): EcommerceQuoteRequest
    {
        ['storefront' => $storefront, 'today' => $today] = $this->stores->openStore($code);

        if (($data['required_on'] ?? null) !== null && $data['required_on'] < $today) {
            throw ValidationException::withMessages(['required_on' => 'Choose today or a later day.']);
        }

        $items    = array_map(fn (array $item, int $at) => $this->item($storefront, $item, $today, $at), $data['items'], array_keys($data['items']));
        $settings = $this->settings->forWorkspace($storefront->workspace);
        $becomes  = $settings->quote_requests_become;
        $uploads  = array_values($data['files'] ?? []);
        $this->checkFiles($storefront, $settings, $uploads);
        // Phase H4d: named in the lead's or the draft quotation's notes, which take no copies.
        $names = array_map(fn (UploadedFile $upload) => $upload->getClientOriginalName(), $uploads);

        [$request, $lead, $quotation] = DB::transaction(function () use ($storefront, $data, $account, $items, $becomes, $uploads, $names) {
            $request = $this->requests->create($storefront, [
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
            ], $items);

            // What Settings → eCommerce says it also becomes: a lead (Phase H4b) or a draft quotation (Phase H4c).
            $lead      = $becomes === EcommerceSetting::BECOME_LEAD ? $this->leads->make($storefront, $request, $names) : null;
            $quotation = $becomes === EcommerceSetting::BECOME_QUOTATION ? $this->quotations->make($storefront, $request, $account, $names) : null;

            if ($lead || $quotation) {
                $this->requests->update($request, ['crm_lead_id' => $lead?->id, 'sales_document_id' => $quotation?->id]);
            }

            // Phase H4d: its files as its attachments - last, so nothing after them can fail.
            if ($uploads !== []) {
                $this->files->store($storefront->workspace, $request, FileCollection::Attachments, $uploads, 'files', byMember: false);
            }

            return [$request, $lead, $quotation];
        });

        $with = match (count($uploads)) {
            0       => '',
            1       => ', with a file',
            default => ', with '.count($uploads).' files',
        };

        SendStoreQuoteRequestEmail::dispatch($request->id)->afterCommit();
        ActivityLogService::recordCustomer($storefront->workspace, 'created', "{$request->customer_name} asked for a quotation on {$storefront->name}: {$request->number}{$with}", $request);

        if ($lead) {
            $this->leads->made($storefront, $request, $lead);
        }

        if ($quotation) {
            $this->quotations->made($storefront, $request, $quotation);
        }

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

    /**
     * Phase H4d: $uploads only where Settings → eCommerce takes files with a
     * request, and only while the business has room for them - each refusal
     * said as its customer may read it.
     *
     * @param  list<UploadedFile>  $uploads
     */
    private function checkFiles(Storefront $storefront, EcommerceSetting $settings, array $uploads): void
    {
        if ($uploads === []) {
            return;
        }

        if (! $settings->quote_request_files) {
            throw ValidationException::withMessages(['files' => 'This store does not take files with a request.']);
        }

        if (! $this->usage->hasRoomFor($storefront->workspace, array_sum(array_map(fn (UploadedFile $upload) => (int) $upload->getSize(), $uploads)))) {
            throw ValidationException::withMessages(['files' => 'This store cannot take files just now: send your request without them.']);
        }
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
