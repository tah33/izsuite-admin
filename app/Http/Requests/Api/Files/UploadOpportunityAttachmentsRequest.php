<?php

namespace App\Http\Requests\Api\Files;

use App\Enums\FileCollection;

/** An opportunity's attachments (Doc 17 Phase D2b): documents and pictures, up to 10 MB each. */
class UploadOpportunityAttachmentsRequest extends UploadFilesRequest
{
    public function collection(): FileCollection
    {
        return FileCollection::Attachments;
    }

    protected function typesMessage(): string
    {
        return 'Attach PDFs, pictures, Word, Excel or PowerPoint files, CSV or text files, or ZIP archives.';
    }
}
