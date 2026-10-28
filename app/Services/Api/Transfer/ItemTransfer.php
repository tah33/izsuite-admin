<?php

namespace App\Services\Api\Transfer;

use App\Enums\ItemLabel;
use App\Http\Requests\Api\Items\SaveItemRequest;
use App\Models\User\Item;
use App\Models\User\ItemComponent;
use App\Models\User\TaxRate;
use App\Models\User\Workspace;
use App\Repositories\User\ItemLabelRepository;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\TaxRateRepository;
use App\Services\Api\Items\ItemService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\LazyCollection;

/**
 * Items in and out of CSV (Doc 04 section 17). Types, usages, delivery
 * methods and warranty settings are written and read by their names
 * ("Physical Product"); categories and brands by name, made when the
 * business does not have them yet. Prices are in the base currency.
 *
 * Combos are exported - with their components - but not imported: a combo is
 * made in the app, from items the business already has. A row without a
 * usage is used both ways; one without a unit gets its type's usual one.
 *
 * A SKU or barcode the business or another row of the file already has
 * cannot be imported; a name it already has is a possible duplicate.
 *
 * While the business uses taxes, the sales and purchase tax go in and out by
 * their codes ("VAT15"): an import names one of the business's active rates
 * for that side, never makes one.
 */
class ItemTransfer implements Transfer
{
    use ReadsCells;

    private const TYPES = [
        Item::TYPE_PHYSICAL => ['physical product', 'product', 'goods', 'stock'],
        Item::TYPE_DIGITAL  => ['digital product', 'digital', 'download'],
        Item::TYPE_SERVICE  => ['services'],
        Item::TYPE_COMBO    => ['bundle', 'kit', 'package'],
    ];

    private const TYPE_LABELS = [
        Item::TYPE_PHYSICAL => 'Physical Product',
        Item::TYPE_DIGITAL  => 'Digital Product',
        Item::TYPE_SERVICE  => 'Service',
        Item::TYPE_COMBO    => 'Combo',
    ];

    /** A unit for a row without one: the type's usual one, as the form starts with. */
    private const UNITS = [
        Item::TYPE_PHYSICAL => 'Piece',
        Item::TYPE_DIGITAL  => 'Licence',
        Item::TYPE_SERVICE  => 'Service',
    ];

    private const USAGES = [
        Item::USAGE_SALES    => ['sales only', 'sale', 'sell', 'selling'],
        Item::USAGE_PURCHASE => ['purchase only', 'purchases', 'buy', 'buying'],
        Item::USAGE_BOTH     => ['sales and purchase', 'sales & purchase', 'all'],
    ];

    private const DELIVERY = [
        'manual'        => ['manual delivery'],
        'download'      => ['downloadable file', 'downloadable', 'file'],
        'license_key'   => ['license key', 'licence key', 'licence', 'license', 'key'],
        'external_link' => ['external link', 'link', 'url'],
    ];

    private const DELIVERY_LABELS = [
        'manual'        => 'Manual Delivery',
        'download'      => 'Downloadable File',
        'license_key'   => 'License Key',
        'external_link' => 'External Link',
    ];

    private const WARRANTY_UNITS = ['days' => ['day', 'd'], 'months' => ['month', 'm'], 'years' => ['year', 'y']];

    private const WARRANTY_STARTS = [
        'invoice_date'      => ['invoice date', 'invoice', 'sale', 'sale date'],
        'delivery_date'     => ['delivery date', 'delivery'],
        'manual_activation' => ['manual activation date', 'manual activation', 'activation', 'manual'],
    ];

    private const WARRANTY_START_LABELS = [
        'invoice_date'      => 'Invoice Date',
        'delivery_date'     => 'Delivery Date',
        'manual_activation' => 'Manual Activation Date',
    ];

    private const STATUSES = ['active' => ['yes', 'enabled'], 'inactive' => ['no', 'disabled']];

    /** The rates an import has looked up, by business and code: a file names the same few again and again. */
    private array $taxes = [];

    public function __construct(
        protected ItemRepository $itemRepository,
        protected ItemService $itemService,
        protected ItemLabelRepository $itemLabelRepository,
        protected TaxRateRepository $taxRateRepository,
    ) {}

    public function plural(): string
    {
        return 'items';
    }

    public function fields(Workspace $workspace): array
    {
        $price = "In the base currency ({$workspace->base_currency}), such as 149.99.";
        $taxes = $workspace->usesTaxes();

        return [
            'type'                  => $this->field('Item Type', true, 'Physical Product, Digital Product or Service. Combos are made in the app.', ['type']),
            'usage'                 => $this->field('Item Usage', hint: 'Sales, Purchase or Both. Empty means Both.', aliases: ['usage']),
            'name'                  => $this->field('Item Name', true, aliases: ['name', 'item', 'product', 'product name', 'service name']),
            'unit'                  => $this->field('Unit of Measure', hint: 'Empty means the type\'s usual unit: Piece, Licence or Service.', aliases: ['unit', 'uom', 'units']),
            'sku'                   => $this->field('SKU', aliases: ['item code', 'product code', 'code']),
            'barcode'               => $this->field('Barcode', aliases: ['ean', 'upc', 'gtin']),
            'category'              => $this->field('Category', hint: 'Made if the business does not have it yet.'),
            'brand'                 => $this->field('Brand', hint: 'Made if the business does not have it yet.'),
            'description'           => $this->field('General Description', aliases: ['description']),
            'sale_price'            => $this->field('Sales Price', hint: $price, aliases: ['price', 'sale price', 'selling price', 'unit price']),
            ...($taxes ? ['sales_tax' => $this->field('Sales Tax', hint: 'The code of one of your sales taxes, such as VAT15.', aliases: ['tax', 'vat', 'gst', 'tax code'])] : []),
            'sales_description'     => $this->field('Sales Description'),
            'purchase_price'        => $this->field('Purchase Price', hint: $price, aliases: ['cost', 'cost price', 'buying price']),
            ...($taxes ? ['purchase_tax' => $this->field('Purchase Tax', hint: 'The code of one of your purchase taxes, such as VAT15.', aliases: ['input tax', 'purchase tax code'])] : []),
            'purchase_description'  => $this->field('Purchase Description'),
            'delivery_method'       => $this->field('Delivery Method', hint: 'Manual Delivery, Downloadable File, License Key or External Link.'),
            'delivery_instructions' => $this->field('Delivery Instructions'),
            'warranty_available'    => $this->field('Warranty Available', hint: 'Yes or No. A warranty period alone means Yes.', aliases: ['warranty']),
            'warranty_period'       => $this->field('Warranty Period', hint: 'A whole number, such as 12.'),
            'warranty_unit'         => $this->field('Warranty Unit', hint: 'Days, Months or Years.'),
            'warranty_start_rule'   => $this->field('Warranty Starts On', hint: 'Invoice Date, Delivery Date or Manual Activation Date.'),
            'warranty_terms'        => $this->field('Warranty Terms'),
            'status'                => $this->field('Status', hint: 'Active or Inactive. Empty means Active.'),
        ];
    }

    public function example(): array
    {
        return [
            'type'                => 'Physical Product',
            'usage'               => 'Both',
            'name'                => 'Office Chair',
            'unit'                => 'Piece',
            'sku'                 => 'CHR-001',
            'barcode'             => '8901234567890',
            'category'            => 'Furniture',
            'sale_price'          => '149.99',
            'purchase_price'      => '95.50',
            'warranty_available'  => 'Yes',
            'warranty_period'     => '12',
            'warranty_unit'       => 'Months',
            'warranty_start_rule' => 'Invoice Date',
            'status'              => 'Active',
        ];
    }

    public function exportColumns(Workspace $workspace): array
    {
        return [
            'Item Number'           => fn (Item $item) => $item->item_number,
            'Item Type'             => fn (Item $item) => self::TYPE_LABELS[$item->type] ?? $item->type,
            'Item Usage'            => fn (Item $item) => ucfirst($item->usage),
            'Item Name'             => fn (Item $item) => $item->name,
            'Unit of Measure'       => fn (Item $item) => $item->unit,
            'SKU'                   => fn (Item $item) => $item->sku,
            'Barcode'               => fn (Item $item) => $item->barcode,
            'Category'              => fn (Item $item) => $item->category?->name,
            // The brand only while the business uses brands, as on its pages.
            ...($workspace->brands_enabled ? ['Brand' => fn (Item $item) => $item->brand?->name] : []),
            'General Description'   => fn (Item $item) => $item->description,
            'Sales Price'           => fn (Item $item) => $item->isCombo() && $item->combo_pricing === 'sum' ? $item->componentTotal() : $item->sale_price,
            // The taxes only while the business uses taxes, as on its pages.
            ...($workspace->usesTaxes() ? ['Sales Tax' => fn (Item $item) => $item->salesTax?->code] : []),
            'Sales Description'     => fn (Item $item) => $item->sales_description,
            'Purchase Price'        => fn (Item $item) => $item->purchase_price,
            ...($workspace->usesTaxes() ? ['Purchase Tax' => fn (Item $item) => $item->purchaseTax?->code] : []),
            'Purchase Description'  => fn (Item $item) => $item->purchase_description,
            'Delivery Method'       => fn (Item $item) => self::DELIVERY_LABELS[$item->delivery_method] ?? null,
            'Delivery Instructions' => fn (Item $item) => $item->delivery_instructions,
            'Warranty Available'    => fn (Item $item) => (bool) $item->warranty_available,
            'Warranty Period'       => fn (Item $item) => $item->warranty_period,
            'Warranty Unit'         => fn (Item $item) => $item->warranty_unit ? ucfirst($item->warranty_unit) : null,
            'Warranty Starts On'    => fn (Item $item) => self::WARRANTY_START_LABELS[$item->warranty_start_rule] ?? null,
            'Warranty Terms'        => fn (Item $item) => $item->warranty_terms,
            'Pricing Method'        => fn (Item $item) => match ($item->combo_pricing) {
                'fixed' => 'Fixed Combo Price',
                'sum'   => 'Sum of Component Prices',
                default => null,
            },
            'Component Display'     => fn (Item $item) => match ($item->combo_display) {
                'combo_only'           => 'Display Combo Only',
                'combo_and_components' => 'Display Combo and Components',
                default                => null,
            },
            // "I-00001 x 2; I-00002 x 1.5"
            'Components'            => fn (Item $item) => $item->isCombo()
                ? $item->components->map(fn (ItemComponent $row) => $row->component?->item_number.' x '.rtrim(rtrim((string) $row->quantity, '0'), '.'))->implode('; ')
                : null,
            'Status'                => fn (Item $item) => $item->is_active ? 'Active' : 'Inactive',
        ];
    }

    public function exportCount(Workspace $workspace, array $filters): int
    {
        return $this->itemRepository->countFor($workspace, $filters, (bool) $workspace->brands_enabled);
    }

    public function exportRecords(Workspace $workspace, array $filters): LazyCollection
    {
        return $this->itemRepository->lazyFor($workspace, $filters, (bool) $workspace->brands_enabled);
    }

    public function payload(Workspace $workspace, array $cells): array
    {
        $errors  = [];
        $payload = [];

        foreach (['name', 'sku', 'barcode', 'description', 'sales_description', 'purchase_description', 'delivery_instructions', 'warranty_terms', 'category', 'brand'] as $key) {
            $payload[$key] = $this->text($cells, $key);
        }

        $type = $this->choice($this->text($cells, 'type'), self::TYPES);

        if ($type === false || $type === null) {
            $errors['type'] = $type === null ? 'Every item needs its type.' : 'Write Physical Product, Digital Product or Service.';
        } elseif ($type === Item::TYPE_COMBO) {
            $errors['type'] = 'Combos are not imported: make them in the app, from items you already have.';
        }

        // A row whose type cannot be imported is checked as a product, so the
        // type is the one thing it is told - not a combo's missing components.
        $payload['type'] = isset(self::UNITS[$type ?: '']) ? $type : Item::TYPE_PHYSICAL;
        $payload['unit'] = $this->text($cells, 'unit') ?? self::UNITS[$payload['type']];

        $usage            = $this->choice($this->text($cells, 'usage'), self::USAGES);
        $payload['usage'] = $usage ?: Item::USAGE_BOTH;

        if ($usage === false) {
            $errors['usage'] = 'Write Sales, Purchase or Both.';
        }

        $payload['sale_price']     = $this->amount($this->text($cells, 'sale_price'));
        $payload['purchase_price'] = $this->amount($this->text($cells, 'purchase_price'));

        if ($workspace->usesTaxes()) {
            foreach (['sales_tax' => TaxRate::SALES, 'purchase_tax' => TaxRate::PURCHASES] as $key => $side) {
                [$payload["{$key}_id"], $error] = $this->tax($workspace, $this->text($cells, $key), $side);

                if ($error) {
                    $errors[$key] = $error;
                }
            }
        }

        $delivery                   = $this->choice($this->text($cells, 'delivery_method'), self::DELIVERY);
        $payload['delivery_method'] = $delivery ?: null;

        if ($delivery === false) {
            $errors['delivery_method'] = 'Write Manual Delivery, Downloadable File, License Key or External Link.';
        }

        // A warranty period on its own says there is a warranty.
        $period    = $this->text($cells, 'warranty_period');
        $available = $this->choice($this->text($cells, 'warranty_available'), self::YES_NO);

        $payload['warranty_available'] = is_string($available) ? $available === 'yes' : $period !== null;
        $payload['warranty_period']    = $period;

        if ($available === false) {
            $errors['warranty_available'] = 'Write Yes or No.';
        }

        $unit                         = $this->choice($this->text($cells, 'warranty_unit'), self::WARRANTY_UNITS);
        $start                        = $this->choice($this->text($cells, 'warranty_start_rule'), self::WARRANTY_STARTS);
        $payload['warranty_unit']     = $unit ?: ($payload['warranty_available'] ? 'months' : null);
        $payload['warranty_start_rule'] = $start ?: ($payload['warranty_available'] ? 'invoice_date' : null);

        if ($unit === false) {
            $errors['warranty_unit'] = 'Write Days, Months or Years.';
        }

        if ($start === false) {
            $errors['warranty_start_rule'] = 'Write Invoice Date, Delivery Date or Manual Activation Date.';
        }

        $status               = $this->choice($this->text($cells, 'status'), self::STATUSES);
        $payload['is_active'] = $status !== 'inactive';

        if ($status === false) {
            $errors['status'] = 'Write Active or Inactive.';
        }

        foreach (['category', 'brand'] as $key) {
            if (mb_strlen((string) $payload[$key]) > 100) {
                $errors[$key] = 'A name can be at most 100 characters.';
            }
        }

        return [$payload, $errors];
    }

    public function errors(Workspace $workspace, array $payload): array
    {
        // The category and brand are names here, found or made as the row is added.
        return $this->formErrors(SaveItemRequest::class, array_diff_key($payload, ['category' => true, 'brand' => true]), ['workspace' => $workspace]);
    }

    public function keys(array $payload): array
    {
        return [
            'duplicates' => $payload['name'] ? ['name:'.mb_strtolower($payload['name'])] : [],
            'unique'     => array_filter([
                'sku'     => $payload['sku'] ? 'sku:'.mb_strtolower($payload['sku']) : null,
                'barcode' => $payload['barcode'] ? 'barcode:'.mb_strtolower($payload['barcode']) : null,
            ]),
        ];
    }

    public function duplicatesOf(Workspace $workspace, array $payload): array
    {
        if (! $payload['name']) {
            return [];
        }

        return $this->itemRepository->namedLike($workspace, $payload['name'], 5)
            ->map(fn (Item $item) => ['id' => $item->id, 'number' => $item->item_number, 'name' => $item->name, 'matched_on' => ['name']])
            ->all();
    }

    public function create(Workspace $workspace, array $payload): Model
    {
        return DB::transaction(function () use ($workspace, $payload) {
            $label = fn (ItemLabel $kind, ?string $name) => $name ? $this->itemLabelRepository->findOrCreateNamed($workspace, $kind, $name)->id : null;

            return $this->itemService->import($workspace, [
                ...array_diff_key($payload, ['category' => true, 'brand' => true]),
                'category_id' => $label(ItemLabel::Category, $payload['category']),
                'brand_id'    => $label(ItemLabel::Brand, $payload['brand']),
            ]);
        });
    }

    /**
     * The rate a cell names by its code, for $side: its id, or why the cell
     * cannot be used. An empty cell is no tax.
     *
     * @return array{0: ?int, 1: ?string}
     */
    private function tax(Workspace $workspace, ?string $code, string $side): array
    {
        if ($code === null) {
            return [null, null];
        }

        $rate = $this->taxes[$workspace->id.'|'.mb_strtoupper($code)] ??= $this->taxRateRepository->findByCodeFor($workspace, $code);

        return match (true) {
            $rate === null           => [null, "There is no tax with the code \"{$code}\". Add it under Settings > Taxes first."],
            ! $rate->{"for_{$side}"} => [null, "{$rate->code} is not a ".($side === TaxRate::SALES ? 'sales' : 'purchase').' tax.'],
            ! $rate->is_active       => [null, "{$rate->code} is inactive."],
            default                  => [$rate->id, null],
        };
    }

    /** @return array{label: string, required: bool, aliases: list<string>, hint: ?string} */
    private function field(string $label, bool $required = false, ?string $hint = null, array $aliases = []): array
    {
        return ['label' => $label, 'required' => $required, 'aliases' => $aliases, 'hint' => $hint];
    }
}
