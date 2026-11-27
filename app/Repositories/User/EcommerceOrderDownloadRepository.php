<?php

namespace App\Repositories\User;

use App\Models\User\EcommerceOrderDelivery;
use App\Models\User\EcommerceOrderDownload;
use App\Models\User\StorefrontCustomer;
use App\Models\User\WorkspaceFile;

/**
 * Digital products' downloads by their customers (Doc 19 section 19.28 -
 * Phase G3b): how often a file was downloaded since its count started, and
 * one download recorded.
 */
class EcommerceOrderDownloadRepository
{
    /** How many times $delivery's file $fileId was downloaded since its count started. */
    public function countOf(EcommerceOrderDelivery $delivery, int $fileId): int
    {
        return EcommerceOrderDownload::query()
            ->where('ecommerce_order_delivery_id', $delivery->id)
            ->where('workspace_file_id', $fileId)
            ->when($delivery->counted_from, fn ($query, $from) => $query->where('downloaded_at', '>=', $from))
            ->count();
    }

    /** $from: the ip and agent of the request that downloaded it. */
    public function record(EcommerceOrderDelivery $delivery, WorkspaceFile $file, ?StorefrontCustomer $customer, array $from): EcommerceOrderDownload
    {
        return EcommerceOrderDownload::query()->create([
            'workspace_id'                => $delivery->workspace_id,
            'ecommerce_order_delivery_id' => $delivery->id,
            'workspace_file_id'           => $file->id,
            'file_name'                   => mb_substr($file->name, 0, 255),
            'storefront_customer_id'      => $customer?->id,
            'ip_address'                  => $from['ip'] ?? null,
            'user_agent'                  => isset($from['agent']) ? mb_substr((string) $from['agent'], 0, 255) : null,
            'downloaded_at'               => now(),
        ]);
    }
}
