<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ReorderContentItemsRequest;
use App\Http\Requests\Admin\StoreContentItemRequest;
use App\Http\Requests\Admin\UpdateContentItemRequest;
use App\Http\Requests\Admin\UploadContentImageRequest;
use App\Models\Frontend\ContentItem;
use App\Models\Admin\Setting;
use App\Services\Shared\ActivityLogService;
use App\Services\Support\ImageService;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Cache;

class ContentController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
    ) {}

    /**
     * Section definitions.
     *
     * 'settings' sections  → stored in settings table (key-value)
     * 'items' sections     → stored in content_items table (repeatable CRUD)
     */
    private const SETTINGS_SECTIONS = [
        'hero'       => [
            'label'  => 'Hero',
            'icon'   => 'layout',
            'fields' => [
                'hero_badge_text'      => ['label' => 'Badge Text', 'type' => 'text'],
                'hero_title'           => ['label' => 'Title', 'type' => 'text'],
                'hero_title_highlight' => ['label' => 'Title Highlight', 'type' => 'text'],
                'hero_description'     => ['label' => 'Description', 'type' => 'textarea'],
                'hero_primary_cta'     => ['label' => 'Primary CTA', 'type' => 'text'],
                'hero_secondary_cta'   => ['label' => 'Secondary CTA', 'type' => 'text'],
                'hero_image'           => ['label' => 'Hero Image', 'type' => 'image', 'hint' => 'Recommended: 800×520px'],
                'hero_savings_label'   => ['label' => 'Savings Label', 'type' => 'text'],
                'hero_savings_amount'  => ['label' => 'Savings Amount', 'type' => 'text'],
                'hero_checkmarks'      => ['label' => 'Checkmarks (one per line)', 'type' => 'textarea'],
            ],
        ],
        'cta'        => [
            'label'  => 'CTA',
            'icon'   => 'megaphone',
            'fields' => [
                'cta_title'       => ['label' => 'CTA Title', 'type' => 'text'],
                'cta_description' => ['label' => 'CTA Description', 'type' => 'textarea'],
                'cta_button_text' => ['label' => 'Button Text', 'type' => 'text'],
                'cta_button_url'  => ['label' => 'Button URL', 'type' => 'text'],
            ],
        ],
        'footer'     => [
            'label'  => 'Footer',
            'icon'   => 'panel-bottom',
            'fields' => [
                'footer_description' => ['label' => 'Footer Description', 'type' => 'textarea'],
                'footer_copyright'   => ['label' => 'Copyright Text', 'type' => 'text'],
            ],
        ],
        'appearance' => [
            'label'     => 'Appearance',
            'icon'      => 'palette',
            'custom_ui' => 'colors',
            'fields'    => [
                'color_preset'      => ['label' => 'Color Preset', 'type' => 'hidden'],
                'color_primary'     => ['label' => 'Primary Color', 'type' => 'text', 'placeholder' => '160 60% 40%'],
                'color_accent'      => ['label' => 'Accent Color', 'type' => 'text', 'placeholder' => '38 92% 50%'],
                'color_destructive' => ['label' => 'Destructive Color', 'type' => 'text', 'placeholder' => '0 72% 51%'],
                'color_success'     => ['label' => 'Success Color', 'type' => 'text', 'placeholder' => '160 60% 40%'],
                'color_warning'     => ['label' => 'Warning Color', 'type' => 'text', 'placeholder' => '38 92% 50%'],
            ],
        ],
        'seo'        => [
            'label'  => 'Home SEO',
            'icon'   => 'search',
            'fields' => [
                'seo_title'       => ['label' => 'Meta Title', 'type' => 'text', 'placeholder' => 'Your site title for search engines'],
                'seo_description' => ['label' => 'Meta Description', 'type' => 'textarea', 'placeholder' => 'A brief description of your site for search results'],
                'seo_keywords'    => ['label' => 'Meta Keywords', 'type' => 'text', 'placeholder' => 'keyword1, keyword2, keyword3'],
            ],
        ],
    ];

    private const ITEMS_SECTIONS    = [
        'stats'          => [
            'label'      => 'Stats',
            'icon'       => 'bar-chart-3',
            'item_label' => 'Stat',
            'fields'     => [
                'value' => ['label' => 'Value', 'type' => 'text', 'placeholder' => 'e.g. $2.4M+'],
                'label' => ['label' => 'Label', 'type' => 'text', 'placeholder' => 'e.g. Saved by users'],
            ],
        ],
        'features'       => [
            'label'      => 'Features',
            'icon'       => 'sparkles',
            'item_label' => 'Feature',
            'fields'     => [
                'icon'        => ['label' => 'Icon', 'type' => 'text', 'placeholder' => 'e.g. search'],
                'title'       => ['label' => 'Title', 'type' => 'text', 'placeholder' => 'e.g. Auto-Detection'],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'placeholder' => 'Describe this feature...'],
            ],
        ],
        'how_it_works'   => [
            'label'      => 'How It Works',
            'icon'       => 'list-ordered',
            'item_label' => 'Step',
            'fields'     => [
                'title'       => ['label' => 'Title', 'type' => 'text', 'placeholder' => 'e.g. Connect Your Accounts'],
                'description' => ['label' => 'Description', 'type' => 'textarea', 'placeholder' => 'Describe this step...'],
            ],
        ],
        'testimonials'   => [
            'label'      => 'Testimonials',
            'icon'       => 'message-circle',
            'item_label' => 'Testimonial',
            'fields'     => [
                'name'   => ['label' => 'Name', 'type' => 'text', 'placeholder' => 'e.g. Sarah Johnson'],
                'role'   => ['label' => 'Role', 'type' => 'text', 'placeholder' => 'e.g. CEO at Acme'],
                'quote'  => ['label' => 'Quote', 'type' => 'textarea', 'placeholder' => 'What they said...'],
                'rating' => ['label' => 'Rating (1-5)', 'type' => 'number', 'placeholder' => '5', 'min' => 1, 'max' => 5],
                'avatar' => ['label' => 'Avatar', 'type' => 'image', 'placeholder' => '', 'hint' => 'Recommended: 80×80px'],
            ],
        ],
        'social_links'   => [
            'label'      => 'Footer Social Links',
            'icon'       => 'share-2',
            'item_label' => 'Social Link',
            'fields'     => [
                'platform' => ['label' => 'Platform', 'type' => 'text', 'placeholder' => 'e.g. Twitter, GitHub, LinkedIn'],
                'url'      => ['label' => 'URL', 'type' => 'text', 'placeholder' => 'e.g. https://twitter.com/yourhandle'],
                'icon'     => ['label' => 'Icon (SVG or Lucide name)', 'type' => 'text', 'placeholder' => 'e.g. twitter, github, linkedin'],
            ],
        ],
        'footer_columns' => [
            'label'      => 'Footer Link Columns',
            'icon'       => 'columns-3',
            'item_label' => 'Column',
            'custom_ui'  => true,
            'fields'     => [
                'name'  => ['label' => 'Column Name', 'type' => 'text', 'placeholder' => 'e.g. Product'],
                'links' => ['label' => 'Links', 'type' => 'links'],
            ],
        ],
    ];

    /**
     * Show the content management page.
     */
    public function index(Request $request)
    {
        try {
            $context          = $request->query('context');

            $settingsSections = self::SETTINGS_SECTIONS;
            $itemsSections    = self::ITEMS_SECTIONS;
            $pageTitle        = 'Content Management';
            $pageSubtitle     = 'Manage all dynamic sections of the landing page';
            $tabOrder         = ['hero', 'stats', 'features', 'how_it_works', 'testimonials', 'cta', 'footer', 'social_links', 'footer_columns', 'appearance', 'seo'];

            if ($context === 'header-footer') {
                $settingsSections = Arr::only($settingsSections, ['branding', 'footer']);
                $itemsSections    = Arr::only($itemsSections, ['social_links', 'footer_columns']);
                $pageTitle        = 'Header & Footer';
                $pageSubtitle     = 'Manage only the header and footer content used across the frontend';
                $tabOrder         = ['footer', 'social_links', 'footer_columns'];
            }

            $settingsValues   = Setting::where('group', 'frontend')->pluck('value', 'key')->toArray();

            $items            = [];
            foreach (array_keys($itemsSections) as $section) {
                $items[$section] = ContentItem::forSection($section);
            }

            return view('admin.content.index', compact(
                'settingsSections', 'itemsSections', 'settingsValues', 'items', 'context', 'pageTitle', 'pageSubtitle', 'tabOrder'
            ));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Save settings-based sections (Hero, CTA, Footer).
     */
    public function update(Request $request)
    {
        try {
            $input       = $request->input('content', []);

            // Save text/textarea fields
            foreach ($input as $key => $value) {
                Setting::set($key, $value ?? '', 'frontend');
            }

            // Handle image uploads
            $imageFields = $this->getImageFieldKeys();
            foreach ($imageFields as $key) {
                if ($request->hasFile("images.{$key}")) {
                    $storedImage = $this->imageService->storePublicWithUrl(
                        $request->file("images.{$key}"),
                        'content',
                        Setting::get($key)
                    );

                    Setting::set($key, $storedImage['url'], 'frontend');
                }
            }

            ActivityLogService::record('updated', 'Updated frontend content');

            // Update the CSS version to bust browser cache
            Setting::set('dynamic_css_version', now()->timestamp, 'frontend');

            // Clear the dynamic CSS cache if we modified any color settings
            Cache::forget('dynamic_css_variables');

            return back()->with('success', __('Content saved successfully.'));

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Upload an image for a content item (AJAX).
     */
    public function uploadImage(UploadContentImageRequest $request)
    {
        try {
            $storedImage = $this->imageService->storePublicWithUrl($request->file('image'), 'content');

            return response()->json([
                'success' => true,
                'path'    => $storedImage['path'],
                'url'     => $storedImage['url'],
            ]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Get all image-type field keys from settings sections.
     */
    private function getImageFieldKeys(): array
    {
        $keys = [];
        foreach (self::SETTINGS_SECTIONS as $section) {
            foreach ($section['fields'] as $key => $field) {
                if ($field['type'] === 'image') {
                    $keys[] = $key;
                }
            }
        }

        return $keys;
    }

    /**
     * Add a new content item to a section (AJAX).
     */
    public function storeItem(StoreContentItemRequest $request)
    {
        try {
            $maxOrder = ContentItem::where('section', $request->section)->max('sort_order') ?? 0;

            $item     = ContentItem::create([
                'section'    => $request->section,
                'data'       => $request->data,
                'sort_order' => $maxOrder + 1,
            ]);

            ActivityLogService::record('created', "Added {$request->section} content item");

            return response()->json(['success' => true, 'item' => $item]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Update a content item (AJAX).
     */
    public function updateItem(UpdateContentItemRequest $request, int $id)
    {
        try {
            $item = ContentItem::findOrFail($id);

            $item->update(['data' => $request->data]);

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Delete a content item (AJAX).
     */
    public function destroyItem(int $id)
    {
        try {
            $item    = ContentItem::findOrFail($id);
            $section = $item->section;
            $item->delete();

            ActivityLogService::record('deleted', "Removed {$section} content item");

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }

    /**
     * Reorder content items (AJAX).
     */
    public function reorderItems(ReorderContentItemsRequest $request)
    {
        try {
            foreach ($request->ids as $index => $id) {
                ContentItem::where('id', $id)->update(['sort_order' => $index]);
            }

            return response()->json(['success' => true]);

        } catch (\Throwable $e) {
            report($e);
            throw $e;
        }
    }
}
