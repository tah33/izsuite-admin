<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\Storefront;
use App\Models\User\StorefrontPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront's pages as its Pages list shows them (Doc 19 Phase C5a): the
 * storefront's name, code and status, and each page's kind, title, link and
 * status - none while it is unwritten - whether the footer links to it, and
 * when it last changed. From Phase C5b, the business's own pages too, in
 * their places, and how many of them the plan allows on each storefront
 * (null for no limit).
 */
class StorefrontPagesResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Storefront $storefront */
        $storefront = $this->resource['storefront'];

        return [
            'storefront' => ['id' => $storefront->id, 'name' => $storefront->name, 'code' => $storefront->code, 'status' => $storefront->status],
            'pages'      => $this->resource['pages']->map(fn (StorefrontPage $page) => [
                'kind'       => $page->kind,
                'title'      => $page->title,
                'slug'       => $page->slug,
                'status'     => $page->exists ? $page->status : null,
                'in_footer'  => $page->in_footer,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ])->values(),
            'own'        => $this->resource['own']->map(fn (StorefrontPage $page) => [
                'id'         => $page->id,
                'title'      => $page->title,
                'slug'       => $page->slug,
                'status'     => $page->status,
                'in_footer'  => $page->in_footer,
                'position'   => $page->position,
                'updated_at' => $page->updated_at?->toIso8601String(),
            ])->values(),
            'limit'      => ['used' => $this->resource['own']->count(), 'limit' => $this->resource['limit']],
        ];
    }
}
