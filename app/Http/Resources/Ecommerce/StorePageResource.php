<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\StorefrontPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A page of a storefront as its customers see it (Doc 19 Phase C5a): its
 * kind, title and words - cleaned when they were kept - its SEO title (its
 * title unless it has one) and description, and, for its contact page, the
 * store's contact details.
 */
class StorePageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var StorefrontPage $page */
        $page = $this->resource['page'];

        return [
            'kind'    => $page->kind,
            'title'   => $page->title,
            'body'    => $page->body,
            'contact' => $this->resource['contact'],
            'seo'     => ['title' => $page->seo_title ?? $page->title, 'description' => $page->seo_description],
        ];
    }
}
