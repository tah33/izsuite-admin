<?php

namespace App\Http\Resources\Ecommerce;

use App\Models\User\Storefront;
use App\Models\User\WorkspaceFile;
use App\Services\Files\FileStorage;
use App\Support\StorefrontTheme;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A storefront's theme as its editor shows it (Doc 19 Phase C4a): the
 * storefront's name, code and status, its draft and the theme published -
 * when, and whether the draft differs - its own pictures to choose among, and
 * whether "Powered by izSuite" shows, by the plan. From Phase C4b, the
 * business's categories and collections its home's sections may name.
 */
class StorefrontThemeResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        /** @var Storefront $storefront */
        $storefront = $this->resource['storefront'];
        $draft      = StorefrontTheme::of($storefront->theme_draft ?? $storefront->theme);
        $published  = StorefrontTheme::of($storefront->theme);
        $storage    = app(FileStorage::class);

        return [
            'storefront'   => ['id' => $storefront->id, 'name' => $storefront->name, 'code' => $storefront->code, 'status' => $storefront->status],
            'draft'        => $draft,
            'published'    => $published,
            'published_at' => $storefront->theme_published_at?->toIso8601String(),
            'changed'      => $draft != $published,
            'pictures'     => $this->resource['pictures']->map(fn (WorkspaceFile $file) => ['id' => $file->id, 'url' => $storage->url($file), 'name' => $file->name])->values(),
            'powered_by'   => $this->resource['powered_by'],
            'choices'      => [
                'categories'  => $this->resource['categories']->map(fn ($category) => ['id' => $category->id, 'name' => $category->name])->values(),
                'collections' => $this->resource['collections']->map(fn ($collection) => ['id' => $collection->id, 'name' => $collection->name])->values(),
            ],
        ];
    }
}
