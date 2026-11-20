<?php

namespace App\Services\Api\Ecommerce;

use App\Models\User\Storefront;
use App\Models\User\StorefrontPage;
use App\Models\User\Workspace;
use App\Repositories\User\StorefrontPageRepository;
use App\Repositories\User\StorefrontRepository;
use App\Services\Shared\ActivityLogService;
use App\Services\Shared\WorkspaceAccess;
use App\Services\User\Workspaces\PlanLimits;
use App\Support\RichText;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * A storefront's pages (Doc 19 section 19.6 - Phase C5a), under Manage
 * Themes and Pages: its contact and about pages and its four policies -
 * privacy, terms and conditions, shipping, and return and refund.
 *
 * Each starts unwritten, with its title, at its link - the same in every
 * store. Its words are rich text with headings, cleaned as they are kept. A
 * page is published or a draft; a draft is seen by its team previewing the
 * store alone. A policy, or the about page, is published only with words in
 * it; the contact page shows the store's contact details whatever it says.
 * Each change is logged.
 *
 * Phase C5b: pages of the business's own too, up to the plan's limit on each
 * storefront - each at the link it is given (from its title, when none is),
 * which no other page of the storefront has and no standard page keeps, in
 * its place in the footer; deleted when they are no longer wanted.
 */
class StorefrontPageService
{
    public function __construct(
        protected StorefrontRepository $storefronts,
        protected StorefrontPageRepository $pages,
        protected PlanLimits $planLimits,
        protected WorkspaceAccess $access,
    ) {}

    /**
     * Its standard pages - each as kept, or as it starts - in the footer's
     * order; from Phase C5b, its own pages and how many the plan allows.
     *
     * @return array{storefront: Storefront, pages: Collection, own: Collection, limit: ?int}
     */
    public function index(Workspace $workspace, int $id): array
    {
        $storefront = $this->storefront($workspace, $id);
        $kept       = $this->pages->standardOf($storefront);

        return [
            'storefront' => $storefront,
            'pages'      => collect(array_keys(StorefrontPage::STANDARD))->map(fn (string $kind) => $kept->get($kind) ?? $this->unwritten($storefront, $kind)),
            'own'        => $this->pages->own($storefront),
            'limit'      => $this->planLimits->storefrontPageLimit($workspace),
        ];
    }

    /** @return array{storefront: Storefront, page: StorefrontPage} its page $page - a kind, as kept or as it starts, or the id of one of its own */
    public function show(Workspace $workspace, int $id, string $page): array
    {
        $storefront = $this->storefront($workspace, $id);

        return ['storefront' => $storefront, 'page' => $this->find($storefront, $page)];
    }

    /**
     * $data is validated (see SaveStorefrontPageRequest): its page $page kept
     * - written for the first time, or changed.
     *
     * @return array{storefront: Storefront, page: StorefrontPage}
     */
    public function save(Workspace $workspace, int $id, string $page, array $data): array
    {
        $storefront = $this->storefront($workspace, $id);
        $found      = $this->find($storefront, $page);
        $kept       = $found->exists ? $found : null;

        return $this->kept($storefront, $kept, $found->kind, $this->fields($storefront, $found->kind, $data, $kept));
    }

    /**
     * Phase C5b: a page of the business's own added to it, while the plan
     * has room for another on this storefront.
     *
     * @return array{storefront: Storefront, page: StorefrontPage}
     */
    public function create(Workspace $workspace, int $id, array $data): array
    {
        $storefront = $this->storefront($workspace, $id);

        $this->planLimits->ensureCanAddStorefrontPage($workspace, $storefront);

        return $this->kept($storefront, null, StorefrontPage::OWN, $this->fields($storefront, StorefrontPage::OWN, $data, null));
    }

    /** Phase C5b: a page of its own deleted - its link no longer found. */
    public function delete(Workspace $workspace, int $id, int $pageId): void
    {
        $storefront = $this->storefront($workspace, $id);
        $page       = $this->pages->ownPage($storefront, $pageId) ?? abort(404, 'Page not found.');

        $this->pages->delete($page);

        ActivityLogService::record('deleted', "Deleted the page {$this->quoted($page->title)} of the storefront {$this->quoted($storefront->name)}", $page, ['source' => 'api']);
    }

    /** $data as a page of $kind keeps it - and a page of its own its link and place. */
    private function fields(Storefront $storefront, string $kind, array $data, ?StorefrontPage $page): array
    {
        $body = RichText::page($data['body'] ?? null);

        if ($data['status'] === StorefrontPage::PUBLISHED && $body === null && $kind !== StorefrontPage::CONTACT) {
            throw ValidationException::withMessages(['body' => 'Write the page before publishing it.']);
        }

        $fields = [
            'title'           => trim($data['title']),
            'body'            => $body,
            'status'          => $data['status'],
            'in_footer'       => (bool) $data['in_footer'],
            'seo_title'       => $this->text($data['seo_title'] ?? null),
            'seo_description' => $this->text($data['seo_description'] ?? null),
        ];

        if ($kind === StorefrontPage::OWN) {
            $fields['slug']     = $this->slugOf($storefront, $data, $page);
            $fields['position'] = isset($data['position']) ? (int) $data['position'] : ($page?->position ?? $this->pages->nextPosition($storefront));
        }

        return $fields;
    }

    /**
     * Phase C5b: a page of its own's link - the one given, if no other page
     * has it and no standard page keeps it; else the one it has; else one
     * made from its title, numbered when it is taken.
     */
    private function slugOf(Storefront $storefront, array $data, ?StorefrontPage $page): string
    {
        $given = Str::lower(trim((string) ($data['slug'] ?? '')));

        if ($given === '' && $page) {
            return $page->slug;
        }

        if ($given === '') {
            $base = Str::slug($data['title']) ?: 'page';
            $slug = $base;

            for ($number = 2; $this->reserved($slug) || $this->pages->slugTaken($storefront, $slug); $number++) {
                $slug = "{$base}-{$number}";
            }

            return $slug;
        }

        if ($this->reserved($given)) {
            throw ValidationException::withMessages(['slug' => 'This link is kept for one of its standard pages: choose another.']);
        }

        if ($this->pages->slugTaken($storefront, $given, $page?->id)) {
            throw ValidationException::withMessages(['slug' => 'Another page of this storefront has this link: choose another.']);
        }

        return $given;
    }

    private function reserved(string $slug): bool
    {
        return in_array($slug, array_column(StorefrontPage::STANDARD, 1), true);
    }

    /**
     * $fields kept on $page - or on its new page of $kind - and, when they
     * changed it, logged: added, published, unpublished or updated.
     *
     * @return array{storefront: Storefront, page: StorefrontPage}
     */
    private function kept(Storefront $storefront, ?StorefrontPage $page, string $kind, array $fields): array
    {
        $was  = $page?->status;
        $page = $this->pages->save($storefront, $page, $kind, $fields);

        if ($page->wasRecentlyCreated || $page->wasChanged()) {
            $done = match (true) {
                $was !== StorefrontPage::PUBLISHED && $page->status === StorefrontPage::PUBLISHED => 'Published',
                $was === StorefrontPage::PUBLISHED && $page->status !== StorefrontPage::PUBLISHED => 'Unpublished',
                $was === null                                                                     => 'Added',
                default                                                                           => 'Updated',
            };

            ActivityLogService::record($was === null ? 'created' : 'updated', "{$done} the page {$this->quoted($page->title)} of the storefront {$this->quoted($storefront->name)}", $page, ['source' => 'api']);
        }

        return ['storefront' => $storefront, 'page' => $page];
    }

    /** Its page $page: one of its kinds - as kept, or unwritten - or, from Phase C5b, one of its own by its id. */
    private function find(Storefront $storefront, string $page): StorefrontPage
    {
        if (ctype_digit($page)) {
            return $this->pages->ownPage($storefront, (int) $page) ?? abort(404, 'Page not found.');
        }

        return $this->pages->ofKind($storefront, $page) ?? $this->unwritten($storefront, $page);
    }

    /** Its page of $kind as it starts: unwritten, its title and link, a draft for the footer. */
    private function unwritten(Storefront $storefront, string $kind): StorefrontPage
    {
        [$title, $slug] = StorefrontPage::STANDARD[$kind];

        return (new StorefrontPage(['kind' => $kind, 'title' => $title, 'slug' => $slug, 'status' => StorefrontPage::DRAFT, 'in_footer' => true]))
            ->forceFill(['storefront_id' => $storefront->id]);
    }

    private function text(?string $value): ?string
    {
        return ($value = trim((string) $value)) === '' ? null : $value;
    }

    private function storefront(Workspace $workspace, int $id): Storefront
    {
        return $this->storefronts->findFor($workspace, $id, $this->access->locations()) ?? abort(404, 'Storefront not found.');
    }

    private function quoted(string $name): string
    {
        return '"'.Str::limit($name, 100).'"';
    }
}
