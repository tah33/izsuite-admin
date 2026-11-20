<?php

namespace App\Http\Requests\Api\Files;

use App\Enums\FileCollection;

/** A storefront's own pictures, for its theme (Doc 19 Phase C4a): JPG, PNG or WebP pictures, up to 2 MB each. */
class UploadStorefrontPicturesRequest extends UploadFilesRequest
{
    public function collection(): FileCollection
    {
        return FileCollection::Branding;
    }

    protected function typesMessage(): string
    {
        return 'Pictures must be JPG, PNG or WebP pictures.';
    }
}
