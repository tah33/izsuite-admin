<?php

namespace App\Http\Requests\Api\Files;

use App\Enums\FileCollection;

/** A contact's attachments (Doc 03 section 5): documents and pictures, up to 10 MB each. */
class UploadContactAttachmentsRequest extends UploadFilesRequest
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
