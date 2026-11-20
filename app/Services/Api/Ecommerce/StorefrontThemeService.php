<?php

namespace App\Services\Api\Ecommerce;

use App\Enums\FileCollection;
use App\Models\User\Storefront;
use App\Models\User\Workspace;
use App\Models\User\WorkspaceFile;
use App\Repositories\User\StorefrontRepository;
use App\Repositories\User\StorefrontThemeRepository;
use App\Services\Api\Files\RecordFileService;
use App\Services\Files\FileStorage;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Support\StorefrontTheme;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A storefront's theme and branding (Doc 19 section 19.7 - Phase C4a).
 *
 * Its team changes a draft - the storefront's customers go on seeing the
 * theme published - and previews it on the storefront itself; publishing puts
 * the draft in its place, discarding puts the published one back in the
 * draft's. Its logo and favicon are pictures of its own, uploaded to it -
 * up to twenty to choose among; once published or discarded, those neither
 * theme shows go. "Powered by izSuite" shows unless the plan removes izSuite's
 * branding, as on documents. Each change is logged; none touches its
 * products or orders.
 *
 * Phase C4b: the draft holds its home page's sections too - its banners'
 * pictures among its own, the categories and collections they name its
 * business's.
 */
class StorefrontThemeService
{
    public function __construct(
        protected StorefrontRepository $storefronts,
        protected StorefrontThemeRepository $themes,
        protected RecordFileService $recordFiles,
        protected FileStorage $fileStorage,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * Its theme, its pictures, whether "Powered by izSuite" shows - and, from
     * Phase C4b, the categories and collections its home's sections may name.
     *
     * @return array{storefront: Storefront, pictures: \Illuminate\Database\Eloquent\Collection, powered_by: bool, categories: \Illuminate\Database\Eloquent\Collection, collections: \Illuminate\Database\Eloquent\Collection}
     */
    public function show(Workspace $workspace, int $id): array
    {
        $storefront = $this->storefront($workspace, $id);

        return [
            'storefront'  => $storefront,
            'pictures'    => $this->themes->pictures($storefront),
            'powered_by'  => ! $workspace->terms()?->removes_branding,
            'categories'  => $this->themes->categories($workspace),
            'collections' => $this->themes->collections($workspace),
        ];
    }

    /** $data is validated (see SaveStorefrontThemeRequest): the draft kept. */
    public function saveDraft(Workspace $workspace, int $id, array $data): array
    {
        $storefront = $this->storefront($workspace, $id);

        if ($this->themes->saveDraft($storefront, $this->checked($workspace, $storefront, $data))) {
            ActivityLogService::record('updated', "Updated the draft theme of the storefront {$this->quoted($storefront)}", $storefront, ['source' => 'api']);
        }

        return $this->show($workspace, $id);
    }

    /**
     * Pictures of its own uploaded, for its theme to show.
     *
     * @param  list<UploadedFile>  $uploads
     */
    public function addPictures(Workspace $workspace, int $id, array $uploads, string $field): \Illuminate\Support\Collection
    {
        $storefront = $this->storefront($workspace, $id);

        if ($this->themes->pictureCount($storefront) + count($uploads) > Storefront::MAX_BRANDING) {
            throw ValidationException::withMessages([$field => 'A storefront keeps up to '.Storefront::MAX_BRANDING.' pictures: publish or discard the draft to clear those it does not show.']);
        }

        return $this->recordFiles->add($workspace, $storefront, FileCollection::Branding, $uploads, $field, "storefront {$this->quoted($storefront)}");
    }

    /** The draft published: what its customers see from now on. */
    public function publish(Workspace $workspace, int $id): array
    {
        $storefront = $this->storefront($workspace, $id);

        $this->themes->publish($storefront, StorefrontTheme::of($storefront->theme_draft ?? $storefront->theme));
        $this->clear($storefront);

        ActivityLogService::record('updated', "Published the theme of the storefront {$this->quoted($storefront)}", $storefront, ['source' => 'api']);

        return $this->show($workspace, $id);
    }

    /** The draft put back to the theme published. */
    public function discard(Workspace $workspace, int $id): array
    {
        $storefront = $this->storefront($workspace, $id);

        if ($this->themes->discard($storefront, StorefrontTheme::of($storefront->theme))) {
            ActivityLogService::record('updated', "Discarded the draft theme of the storefront {$this->quoted($storefront)}", $storefront, ['source' => 'api']);
        }

        $this->clear($storefront);

        return $this->show($workspace, $id);
    }

    /**
     * The draft as kept: its pictures its own; words trimmed, blanks none;
     * each network's link once; and, from Phase C4b, its home's sections -
     * the default's when none are given.
     */
    private function checked(Workspace $workspace, Storefront $storefront, array $data): array
    {
        $own    = $this->ids($this->themes->pictures($storefront));
        $text   = fn (?string $value) => ($value = trim((string) $value)) === '' ? null : $value;
        $errors = [];

        foreach (['logo' => $data['logo'] ?? null, 'favicon' => $data['favicon'] ?? null, 'seo.image' => $data['seo']['image'] ?? null] as $key => $id) {
            if ($id !== null && ! in_array((int) $id, $own, true)) {
                $errors[$key] = 'Choose a picture uploaded for this storefront.';
            }
        }

        $home = isset($data['home']) ? $this->sections($workspace, $data['home'], $own, $text, $errors) : StorefrontTheme::DEFAULTS['home'];

        if ($errors) {
            throw ValidationException::withMessages($errors);
        }

        return StorefrontTheme::of([
            'home'    => $home,
            'logo'    => isset($data['logo']) ? (int) $data['logo'] : null,
            'favicon' => isset($data['favicon']) ? (int) $data['favicon'] : null,
            'colors'  => array_map(fn (string $colour) => Str::lower($colour), $data['colors']),
            'font'    => $data['font'],
            'header'  => ['layout' => $data['header']['layout'], 'announcement' => $text($data['header']['announcement'] ?? null)],
            'card'    => ['style' => $data['card']['style'], 'image' => $data['card']['image']],
            'footer'  => ['text' => $text($data['footer']['text'] ?? null)],
            'contact' => ['address' => $text($data['contact']['address'] ?? null), 'hours' => $text($data['contact']['hours'] ?? null)],
            'social'  => collect($data['social'] ?? [])
                ->map(fn (array $link) => ['network' => $link['network'], 'url' => trim($link['url'])])
                ->unique('network')
                ->values()
                ->all(),
            // Phase C6a: listed by search engines unless it says otherwise.
            'seo'     => [
                'title'       => $text($data['seo']['title'] ?? null),
                'description' => $text($data['seo']['description'] ?? null),
                'image'       => isset($data['seo']['image']) ? (int) $data['seo']['image'] : null,
                'index'       => (bool) ($data['seo']['index'] ?? true),
            ],
        ]);
    }

    /**
     * Phase C4b: its home's sections as kept, each with its type's fields
     * alone - and, in $errors by field, a banner's picture not its own, or a
     * category or collection not its business's.
     */
    private function sections(Workspace $workspace, array $sections, array $own, \Closure $text, array &$errors): array
    {
        $categories  = $this->ids($this->themes->categories($workspace));
        $collections = $this->ids($this->themes->collections($workspace));
        $kept        = [];

        foreach (array_values($sections) as $at => $section) {
            $field   = "home.{$at}";
            $type    = $section['type'];
            $heading = $text($section['heading'] ?? null);

            if ($type === 'banner') {
                $kept[] = $this->banner($section, $field, $heading, $own, $categories, $collections, $text, $errors);

                continue;
            }

            $chosen     = $type === 'collections' ? array_map('intval', array_values($section['ids'] ?? [])) : [];
            $collection = $type === 'products' && $section['source'] === 'collection' ? (int) $section['collection'] : null;

            if (array_diff($chosen, $collections)) {
                $errors["{$field}.ids"] = 'Choose collections of this business.';
            }

            if ($collection !== null && ! in_array($collection, $collections, true)) {
                $errors["{$field}.collection"] = 'Choose a collection of this business.';
            }

            $kept[] = match ($type) {
                'collections' => ['type' => $type, 'heading' => $heading, 'ids' => $chosen],
                'categories'  => ['type' => $type, 'heading' => $heading],
                'products'    => ['type' => $type, 'heading' => $heading, 'source' => $section['source'], 'collection' => $collection, 'count' => (int) $section['count']],
                'text'        => ['type' => $type, 'heading' => $heading, 'body' => $text($section['body'] ?? null)],
            };
        }

        return $kept;
    }

    /** Phase C4b: a banner as kept - with no button, its link is the shop. */
    private function banner(array $section, string $field, ?string $heading, array $own, array $categories, array $collections, \Closure $text, array &$errors): array
    {
        $picture = isset($section['picture']) ? (int) $section['picture'] : null;
        $button  = $text($section['button'] ?? null);
        $to      = $button !== null ? ($section['link']['to'] ?? 'shop') : 'shop';
        $id      = $to !== 'shop' && isset($section['link']['id']) ? (int) $section['link']['id'] : null;

        if ($picture !== null && ! in_array($picture, $own, true)) {
            $errors["{$field}.picture"] = 'Choose a picture uploaded for this storefront.';
        }

        if ($to !== 'shop' && $id === null) {
            $errors["{$field}.link.id"] = 'Choose where it goes.';
        } elseif ($to !== 'shop' && ! in_array($id, $to === 'category' ? $categories : $collections, true)) {
            $errors["{$field}.link.id"] = $to === 'category' ? 'Choose a category of this business.' : 'Choose a collection of this business.';
        }

        return ['type' => 'banner', 'heading' => $heading, 'picture' => $picture, 'text' => $text($section['text'] ?? null), 'button' => $button, 'link' => ['to' => $to, 'id' => $id]];
    }

    /** @return list<int> */
    private function ids(\Illuminate\Support\Collection $models): array
    {
        return $models->pluck('id')->map(fn ($id) => (int) $id)->all();
    }

    /** Its pictures neither its draft nor its published theme shows, gone. */
    private function clear(Storefront $storefront): void
    {
        $shown = [...StorefrontTheme::fileIds(StorefrontTheme::of($storefront->theme)), ...StorefrontTheme::fileIds(StorefrontTheme::of($storefront->theme_draft))];

        $this->fileStorage->delete($this->themes->pictures($storefront)->reject(fn (WorkspaceFile $file) => in_array((int) $file->id, $shown, true)));
    }

    private function storefront(Workspace $workspace, int $id): Storefront
    {
        return $this->storefronts->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'Storefront not found.');
    }

    private function quoted(Storefront $storefront): string
    {
        return '"'.Str::limit($storefront->name, 100).'"';
    }
}
