<?php

namespace App\Http\Requests\Api\Files;

use App\Enums\FileCollection;

/** An online product's pictures (Doc 19 Phase B1): JPG, PNG or WebP pictures, up to 5 MB each. */
class UploadCatalogPicturesRequest extends UploadFilesRequest
{
    public function collection(): FileCollection
    {
        return FileCollection::Gallery;
    }

    protected function typesMessage(): string
    {
        return 'Pictures must be JPG, PNG or WebP.';
    }
}
