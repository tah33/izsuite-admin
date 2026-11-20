<?php

namespace App\Services\Api\Pos;

use App\Enums\DocumentType;
use App\Jobs\SendDocumentEmail;
use App\Models\User\DocumentEmail;
use App\Models\User\PosDevice;
use App\Models\User\PosOrder;
use App\Models\User\Workspace;
use App\Repositories\User\DocumentEmailRepository;
use App\Repositories\User\EmailSettingRepository;
use App\Repositories\User\PosDeviceRepository;
use App\Repositories\User\PosOrderRepository;
use App\Repositories\User\PosSettingRepository;
use App\Services\Documents\DocumentEmailText;
use App\Services\Documents\DocumentPdf;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use Illuminate\Support\Facades\DB;

/**
 * A POS sale's receipt (Doc 18 sections 18.19 and 18.20 - Phase C4): its
 * PDF, on a receipt printer's 80 mm roll, to print or save, and e-mailed to
 * the customer. An order is seen at the locations the user may use.
 *
 * The first copy is its cashier's - made as the sale completes. Every later
 * one is a reprint: it needs Reprint Receipts, says Reprint on it, and is
 * logged. The order counts its copies, one at a time.
 *
 * E-mailed - by its cashier, or whoever may reprint receipts - it goes as the
 * original, in the business's receipt e-mail (Settings → Email), and is
 * logged.
 */
class PosReceiptService
{
    public function __construct(
        protected PosOrderRepository $orders,
        protected PosSettingRepository $settings,
        protected EmailSettingRepository $emailSettings,
        protected DocumentEmailRepository $emails,
        protected DocumentEmailText $emailText,
        protected DocumentPdf $documentPdf,
        protected WorkspaceAccess $access,
        protected PosOrderAccess $orderAccess,
        protected PosDeviceRepository $devices,
    ) {}

    /**
     * The next copy, counted once it is made: a copy that could not be made
     * is not counted.
     *
     * @return array{0: string, 1: string} the file name and the PDF
     */
    public function pdf(Workspace $workspace, int $id): array
    {
        $order = $this->orderAccess->find($workspace, $id);

        return DB::transaction(function () use ($workspace, $order): array {
            $copy = $this->orders->lock($order)->receipt_prints + 1;

            if ($copy > 1) {
                abort_unless($this->access->can('pos.receipts.reprint'), 403, "Receipt {$order->number} was printed already: a copy needs Reprint Receipts.");
            } else {
                $this->orderAccess->mayHandle($order);
            }

            $made = $this->documentPdf->posReceipt($workspace, $this->orders->loadForReceipt($order), $copy > 1);

            $this->orders->update($order, ['receipt_prints' => $copy]);
            // Doc 18 Phase I3: its register's receipt printers, used now.
            $this->devices->used((int) $order->pos_register_id, PosDevice::RECEIPT_PRINTER);

            if ($copy > 1) {
                ActivityLogService::record('updated', "Reprinted receipt {$order->number}, copy {$copy}", $order->invoice, ['source' => 'api']);
            }

            return $made;
        });
    }

    /** $data is validated (see EmailPosReceiptRequest): the address it goes to. The order. */
    public function email(Workspace $workspace, int $id, array $data): PosOrder
    {
        $order = $this->orders->loadForReceipt($this->orderAccess->find($workspace, $id));

        $this->orderAccess->mayHandle($order);

        $values   = $this->emailText->forPosReceipt($workspace, $order, $this->settings->forWorkspace($workspace)->walk_in_contact_id);
        $template = $this->emailSettings->forWorkspace($workspace)->template(DocumentType::CustomerReceipt->value);
        $email    = $this->emails->create($workspace, $order, [
            'kind'    => DocumentEmail::DOCUMENT,
            'to'      => $data['to'],
            'cc'      => [],
            'subject' => mb_substr($this->emailText->fill($template['subject'], $values), 0, 255),
            'sent_by' => $this->access->user()->id,
        ]);

        SendDocumentEmail::dispatch($email->id, $this->emailText->fill($template['body'], $values));

        ActivityLogService::record('updated', "Emailed receipt {$order->number} to {$data['to']}", $order->invoice, ['source' => 'api']);

        return $order;
    }

}
