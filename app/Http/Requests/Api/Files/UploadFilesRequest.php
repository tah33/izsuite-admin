<?php

namespace App\Http\Requests\Api\Files;

use App\Enums\FileCollection;
use App\Services\Files\FileLimits;
use App\Support\Bytes;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Files uploaded to one collection of a record, checked by what the
 * collection allows (see FileCollection): their size, and their type by
 * their content - a file named .pdf has to be a PDF.
 *
 * A collection that holds one file takes it as a single field named after it
 * ("image"); the others take up to ten at once, as "files". Whether the
 * business has room for them is the storage's to say, once they pass this.
 */
abstract class UploadFilesRequest extends FormRequest
{
    abstract public function collection(): FileCollection;

    /** The field the upload arrives in, and its errors are keyed by. */
    public function field(): string
    {
        return $this->collection()->isSingle() ? $this->collection()->value : 'files';
    }

    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        // An attachment's types and size are the admin's (Doc 14 section 14.12, FileLimits).
        $collection = $this->collection();
        $limits     = app(FileLimits::class);
        $file       = ['required', 'file', 'max:'.$limits->maxKilobytes($collection)];

        if ($extensions = $limits->extensions($collection)) {
            $file[] = 'mimes:'.implode(',', $extensions);
        }

        if ($collection->isSingle()) {
            return [$this->field() => $file];
        }

        return [
            'files'   => ['required', 'array', 'min:1', 'max:10'],
            'files.*' => $file,
        ];
    }

    /** @return list<\Illuminate\Http\UploadedFile> */
    public function uploads(): array
    {
        $uploads = $this->file($this->field());

        return is_array($uploads) ? array_values($uploads) : [$uploads];
    }

    public function messages(): array
    {
        $collection = $this->collection();
        $limits     = app(FileLimits::class);
        $largest    = Bytes::format($limits->maxKilobytes($collection) * 1024);
        $key        = $collection->isSingle() ? $this->field() : 'files.*';
        $narrowed   = $limits->narrowed($collection);

        return [
            'files.required' => 'Choose a file to upload.',
            'files.max'      => 'Upload up to ten files at a time.',
            "{$key}.required" => 'Choose a file to upload.',
            "{$key}.file"     => 'The upload did not arrive whole. Please try again.',
            "{$key}.uploaded" => "The file could not be uploaded. Files can be up to {$largest}.",
            "{$key}.max"      => "Files can be up to {$largest}.",
            // The admin's narrower choice is named type by type.
            "{$key}.mimes"    => $narrowed ? 'Attach '.FileLimits::named($narrowed).' files.' : $this->typesMessage(),
        ];
    }

    /** What to say when a file is not a type the collection takes. */
    abstract protected function typesMessage(): string;
}
