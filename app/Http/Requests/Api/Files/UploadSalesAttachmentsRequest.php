<?php

namespace App\Http\Requests\Api\Files;

use App\Enums\FileCollection;

/** A sales document's attachments (Doc 08 section 4): documents and pictures, up to 10 MB each. */
class UploadSalesAttachmentsRequest extends UploadFilesRequest
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
