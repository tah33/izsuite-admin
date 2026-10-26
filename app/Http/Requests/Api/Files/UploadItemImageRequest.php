<?php

namespace App\Http\Requests\Api\Files;

use App\Enums\FileCollection;

/** An item's image (Doc 04 section 6): one JPG, PNG or WebP picture, up to 2 MB. */
class UploadItemImageRequest extends UploadFilesRequest
{
    public function collection(): FileCollection
    {
        return FileCollection::Image;
    }

    protected function typesMessage(): string
    {
        return 'The image must be a JPG, PNG or WebP picture.';
    }
}
