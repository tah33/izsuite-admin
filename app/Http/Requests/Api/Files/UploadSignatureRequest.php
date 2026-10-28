<?php

namespace App\Http\Requests\Api\Files;

use App\Enums\FileCollection;

/** A document template's signature or stamp image (Doc 06 section 14): one JPG, PNG or WebP picture, up to 1 MB. */
class UploadSignatureRequest extends UploadFilesRequest
{
    public function collection(): FileCollection
    {
        return FileCollection::Signature;
    }

    protected function typesMessage(): string
    {
        return 'The signature must be a JPG, PNG or WebP picture.';
    }
}
