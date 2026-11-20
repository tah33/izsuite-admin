<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\CatalogCategory;
use App\Models\User\ItemBrand;
use App\Models\User\Storefront;
use App\Models\User\StorefrontPage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront as its customers see it (Doc 19 Phase C1). Not open, only its
 * name and why: closed for maintenance, or simply not open. Open, its
 * currency and whether its prices include tax (null while the business has
 * taxes off), its support contact, its menu's categories and the brands of
 * what it shows (null while the business keeps no brands) - and, from Phase
 * C4a, its theme (StoreThemeResource) and whether it is previewed; from Phase
 * C5a, the titles and links of the pages its footer links to; from Phase C6b,
 * its cookie notice - its words, none for izSuite's own, and whether its
 * privacy policy is published to link to - or null when it shows none.
 */
class StoreResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Storefront $storefront */
        $storefront = $this->resource['storefront'];

        if (! $this->resource['open']) {
            return [
                'name'   => $storefront->name,
                'code'   => $storefront->code,
                'open'   => false,
                'reason' => $storefront->status === Storefront::MAINTENANCE ? 'maintenance' : 'closed',
            ];
        }

        $terms = $this->resource['terms'];

        return [
            'name'               => $storefront->name,
            'code'               => $storefront->code,
            'open'               => true,
            // Phase C4a: seen by its team in its draft theme - and whether its customers see it open too - and its theme.
            'preview'            => $this->resource['preview'],
            'open_to_customers'  => $this->resource['customers'],
            'theme'              => (new StoreThemeResource($this->resource['theme']))->resolve($request),
            'currency'           => $terms['currency'],
            'prices_include_tax' => $terms['with_tax'],
            'support'            => ['email' => $storefront->support_email, 'phone' => $storefront->support_phone],
            'menu'               => $this->resource['menu']->map(fn (CatalogCategory $online) => ['name' => $online->category->name, 'slug' => $online->slug])->values(),
            'brands'             => $this->resource['brands']?->map(fn (ItemBrand $brand) => ['id' => $brand->id, 'name' => $brand->name])->values(),
            'pages'              => $this->resource['pages']->map(fn (StorefrontPage $page) => ['title' => $page->title, 'slug' => $page->slug])->values(),
            'cookie_notice'      => $this->resource['settings']->cookie_notice ? ['text' => $this->resource['settings']->cookie_notice_text, 'privacy_policy' => $this->resource['privacy']] : null,
        ];
    }
}
