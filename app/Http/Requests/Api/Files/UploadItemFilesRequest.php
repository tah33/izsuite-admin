<?php

namespace App\Http\Requests\Api\Files;

use App\Enums\FileCollection;

/** A digital product's files (Doc 04 section 12): any type, up to 100 MB each. */
class UploadItemFilesRequest extends UploadFilesRequest
{
    public function collection(): FileCollection
    {
        return FileCollection::DigitalFiles;
    }

    protected function typesMessage(): string
    {
        return 'This file cannot be uploaded.';
    }
}
