<?php

namespace App\Http\Controllers;

use App\Services\Admin\Privacy\PrivacyRequestService;

/**
 * A privacy request's data export, by the signed link e-mailed to the person
 * who asked (Doc 15 Phase G1). The signature holds the link's expiry; the
 * file must still be there too.
 */
class PrivacyExportController extends Controller
{
    public function __construct(
        protected PrivacyRequestService $requests,
    ) {}

    public function download(int $id)
    {
        try {
            $privacy = $this->requests->findOrFail($id);

            return response()->download($this->requests->exportFile($privacy), 'izsuite-'.strtolower($privacy->workspace?->businessId() ?? $privacy->number()).'-export.zip');

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
