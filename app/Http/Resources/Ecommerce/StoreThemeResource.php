<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\WorkspaceFile;
use App\Services\Files\FileStorage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront's theme as its pages wear it (Doc 19 Phase C4a): its logo's
 * and favicon's addresses, its colours, font, header, product cards, footer,
 * address, hours and social links, and whether "Powered by izSuite" shows.
 * From the theme (StorefrontTheme), its pictures and the plan. From Phase
 * C6a, its search and sharing: the home's title and description, the sharing
 * picture's address, and whether search engines may list it.
 */
class StoreThemeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $theme    = $this->resource['theme'];
        $pictures = $this->resource['pictures']->keyBy('id');
        $url      = fn (?int $id) => ($file = $id ? $pictures->get($id) : null) instanceof WorkspaceFile ? app(FileStorage::class)->url($file) : null;

        return [
            'logo_url'    => $url($theme['logo']),
            'favicon_url' => $url($theme['favicon']),
            'colors'      => $theme['colors'],
            'font'        => $theme['font'],
            'header'      => $theme['header'],
            'card'        => $theme['card'],
            'footer'      => $theme['footer'],
            'contact'     => $theme['contact'],
            'social'      => $theme['social'],
            'powered_by'  => $this->resource['powered_by'],
            'seo'         => [
                'title'       => $theme['seo']['title'],
                'description' => $theme['seo']['description'],
                'image_url'   => $url($theme['seo']['image']),
                'index'       => $theme['seo']['index'],
            ],
        ];
    }
}
