<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\Workspace;
use App\Repositories\User\EcommerceFulfilmentRepository;
use App\Repositories\User\EcommerceOrderRepository;
use App\Services\Documents\DocumentPdf;

/**
 * A shipment's packing slip (Doc 19 sections 19.27 and 19.39 - Phase G2):
 * the delivery document that goes in the parcel - what it holds, where it
 * goes, and its carrier and tracking - never a price, nor the business's
 * notes. Seen by whoever may view the order where its location may be used,
 * whatever the shipment's state: a cancelled one's says so across it. A
 * pickup has none.
 */
class EcommercePackingSlips
{
    public function __construct(
        protected EcommerceOrderService $orderService,
        protected EcommerceOrderRepository $orders,
        protected EcommerceFulfilmentRepository $fulfilments,
        protected DocumentPdf $documentPdf,
    ) {}

    /** @return array{0: string, 1: string} shipment $fulfilmentId of order $id: its packing slip's file name, and the PDF */
    public function pdf(Workspace $workspace, int $id, int $fulfilmentId): array
    {
        $order    = $this->orderService->find($workspace, $id);
        $shipment = $this->fulfilments->shipmentOf($order, $fulfilmentId) ?? abort(404, 'There is no such shipment.');

        return $this->documentPdf->packingSlip($workspace, $this->orders->forPackingSlip($order), $shipment);
    }
}
