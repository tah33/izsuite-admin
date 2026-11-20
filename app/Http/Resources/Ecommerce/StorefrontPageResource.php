<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\Storefront;
use App\Models\User\StorefrontPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront's page as its editor shows it (Doc 19 Phase C5a): the
 * storefront's name and code, and the page - its kind, title, link, words,
 * status (none while it is unwritten), whether the footer links to it, its
 * SEO, and when it last changed - and, from Phase C5b, its id (none while it
 * is unwritten) and its place in the footer.
 */
class StorefrontPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Storefront $storefront */
        $storefront = $this->resource['storefront'];
        /** @var StorefrontPage $page */
        $page = $this->resource['page'];

        return [
            'storefront' => ['id' => $storefront->id, 'name' => $storefront->name, 'code' => $storefront->code],
            'page'       => [
                'id'              => $page->exists ? $page->id : null,
                'kind'            => $page->kind,
                'title'           => $page->title,
                'slug'            => $page->slug,
                'body'            => $page->body,
                'status'          => $page->exists ? $page->status : null,
                'in_footer'       => $page->in_footer,
                'seo_title'       => $page->seo_title,
                'seo_description' => $page->seo_description,
                'position'        => $page->position,
                'updated_at'      => $page->updated_at?->toIso8601String(),
            ],
        ];
    }
}
