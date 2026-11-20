<?php

namespace App\Http\Requests\Api\Files;

use App\Enums\FileCollection;

/** A catalog category's or collection's picture (Doc 19 Phase B2): one JPG, PNG or WebP picture, up to 2 MB. */
class UploadCatalogImageRequest extends UploadFilesRequest
{
    public function collection(): FileCollection
    {
        return FileCollection::Image;
    }

    protected function typesMessage(): string
    {
        return 'The picture must be a JPG, PNG or WebP picture.';
    }
}
