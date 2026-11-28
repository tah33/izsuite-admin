<?php

namespace App\Http\Requests\Api\Ecommerce;

use App\Enums\FileCollection;
use App\Models\User\EcommerceQuoteRequest;
use App\Services\Files\FileLimits;
use App\Support\Bytes;
use App\Support\Countries;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A request for a quotation from a storefront (Doc 19 Phase H4a): the
 * customer's name, e-mail, phone and company; the delivery location; the
 * date wanted; a message; and the products asked about, with their options
 * and how many - up to 20. From Phase H4d, up to three files, as attachments
 * are taken anywhere (FileLimits) - whether the store takes them at all is
 * StorefrontQuoteRequests' to say.
 */
class SubmitStoreQuoteRequestRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $limits = app(FileLimits::class);
        $file   = ['file', 'max:'.$limits->maxKilobytes(FileCollection::Attachments)];

        if ($extensions = $limits->extensions(FileCollection::Attachments)) {
            $file[] = 'mimes:'.implode(',', $extensions);
        }

        return [
            'name'               => ['required', 'string', 'max:150'],
            'email'              => ['required', 'email', 'max:255'],
            'phone'              => ['nullable', 'string', 'max:40'],
            'company'            => ['nullable', 'string', 'max:150'],
            'delivery'           => ['required', 'array'],
            'delivery.country'   => ['required', 'string', Rule::in(Countries::codes())],
            'delivery.city'      => ['nullable', 'string', 'max:100'],
            'delivery.address'   => ['nullable', 'string', 'max:255'],
            'required_on'        => ['nullable', 'date_format:Y-m-d'],
            'message'            => ['nullable', 'string', 'max:2000'],
            'items'              => ['required', 'array', 'min:1', 'max:'.EcommerceQuoteRequest::MAX_ITEMS],
            'items.*.product'    => ['required', 'string', 'max:200'],
            'items.*.variant_id' => ['nullable', 'integer'],
            'items.*.quantity'   => ['required', 'numeric', 'min:1', 'max:999999'],
            // Phase H4d.
            'files'              => ['nullable', 'array', 'max:'.EcommerceQuoteRequest::MAX_FILES],
            'files.*'            => $file,
        ];
    }

    public function messages(): array
    {
        $limits  = app(FileLimits::class);
        $largest = Bytes::format($limits->maxKilobytes(FileCollection::Attachments) * 1024);
        $types   = $limits->narrowed(FileCollection::Attachments);

        return [
            'name.required'             => 'Give your name.',
            'email.required'            => 'Give an e-mail we can reply to.',
            'email.email'               => 'Give an e-mail we can reply to.',
            'delivery.country.required' => 'Choose the country it is delivered to.',
            'delivery.country.in'       => 'Choose the country it is delivered to.',
            'required_on.date_format'   => 'Give the date as a day.',
            'message.max'               => 'Keep the message to 2000 characters.',
            'items.required'            => 'Choose a product to ask about.',
            'items.max'                 => 'Ask about up to '.EcommerceQuoteRequest::MAX_ITEMS.' products at once.',
            'items.*.quantity.min'      => 'Ask for 1 at least.',
            'items.*.quantity.required' => 'Say how many.',
            'files.max'                 => 'Attach up to '.EcommerceQuoteRequest::MAX_FILES.' files.',
            'files.*.file'              => 'A file did not arrive whole. Please try again.',
            'files.*.uploaded'          => "A file could not be sent. Files can be up to {$largest}.",
            'files.*.max'               => "Files can be up to {$largest}.",
            'files.*.mimes'             => $types ? 'Attach '.FileLimits::named($types).' files.' : 'Attach PDFs, pictures, Word, Excel or PowerPoint files, CSV or text files, or ZIP archives.',
        ];
    }
}
