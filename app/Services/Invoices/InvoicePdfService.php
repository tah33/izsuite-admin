<?php

namespace App\Services\Invoices;

use App\Models\Billing\Invoice;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Log;

class InvoicePdfService
{
    public function download(Invoice $invoice): Response
    {
        $invoice->loadMissing(['user', 'subscription']);

        $filename = $this->filename($invoice);
        $html     = $this->renderTemplate([
            'invoice'          => $invoice,
            'subscription'     => $invoice->subscription,
            'user'             => $invoice->user,
            'use_custom_fonts' => true,
        ]);

        return $this->renderPdf($html, $filename);
    }

    private function renderPdf(string $html, string $filename): Response
    {
        $tempDir = storage_path('app/dompdf-temp');
        $fontDir = storage_path('app/dompdf-fonts');

        if (! is_dir($tempDir) && ! @mkdir($tempDir, 0755, true) && ! is_dir($tempDir)) {
            $tempDir = sys_get_temp_dir();
        }
        if (! is_dir($fontDir) && ! @mkdir($fontDir, 0755, true) && ! is_dir($fontDir)) {
            $fontDir = $tempDir;
        }

        return rescue(
            fn () => Pdf::setOption([
                'isRemoteEnabled'         => true,
                'isHtml5ParserEnabled'    => true,
                // Prevent known dompdf custom-font spacing artifacts on some invoice values.
                'isFontSubsettingEnabled' => false,
                'chroot'                  => base_path(),
                'tempDir'                 => $tempDir,
                'fontDir'                 => $fontDir,
                'fontCache'               => $fontDir,
            ])->loadHTML($html)
                ->setPaper('a4')
                ->download($filename),
            function (\Throwable $e): never {
                Log::error('Invoice PDF generation failed.', [
                    'message' => $e->getMessage(),
                    'trace'   => $e->getTraceAsString(),
                ]);

                abort(500, 'Failed to generate invoice PDF.');
            },
            report: false
        );
    }

    private function renderTemplate(array $data): string
    {
        $template = file_get_contents(resource_path('views/pdf/invoices/recruiter.blade.php'));

        ob_start();
        extract($data, EXTR_SKIP);
        eval('?>'.Blade::compileString($template));

        return (string) ob_get_clean();
    }

    private function filename(Invoice $invoice): string
    {
        $number = preg_replace('/[^A-Za-z0-9_-]/', '-', $invoice->invoice_number ?: "invoice-{$invoice->id}");

        return "{$number}.pdf";
    }
}
