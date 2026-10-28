<?php

namespace App\Http\Requests\Api\Items;

use App\Models\User\Item;
use App\Models\User\TaxRate;
use App\Repositories\User\ItemRepository;
use App\Repositories\User\TaxRateRepository;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveItemRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The same for adding and for editing (Doc 04). The name, type, usage and
     * unit are required. A SKU and a barcode are each unique within the
     * business, and an item being edited may keep its own.
     *
     * Prices are in the business's base currency, never negative, to the
     * cent. The sales and purchase tax are rates of this business for that
     * side (Doc 04 sections 8 to 10). An optional field left out of an update
     * is left as it was - how a form keeps what it hides: the purchase side of
     * an item only sold, a physical product's warranty once it is something
     * else, its taxes while taxes are off.
     *
     * A combo is only sold. It needs its pricing and display methods, two or
     * more components with quantities above zero, and - priced on its own - a
     * sales price.
     */
    public function rules(): array
    {
        $workspace  = $this->attributes->get('workspace');
        $combo      = $this->input('type') === Item::TYPE_COMBO;
        $warranty   = $this->boolean('warranty_available');
        $price      = ['nullable', 'numeric', 'decimal:0,2', 'min:0', 'max:9999999999999.99'];
        $text       = ['nullable', 'string', 'max:5000'];
        $unique     = fn (string $column) => Rule::unique(Item::class, $column)->where('workspace_id', $workspace?->id)->ignore($this->route('id'));
        $inBusiness = fn (string $table) => Rule::exists($table, 'id')->where('workspace_id', $workspace?->id);
        $taxFor     = fn (string $side) => Rule::exists(TaxRate::class, 'id')->where('workspace_id', $workspace?->id)->where("for_{$side}", true);

        return [
            'type'                  => ['required', Rule::in(Item::TYPES)],
            'usage'                 => ['required', Rule::in($combo ? [Item::USAGE_SALES] : Item::USAGES)],
            'name'                  => ['required', 'string', 'max:255'],
            'unit'                  => ['required', 'string', 'max:20'],
            'sku'                   => ['nullable', 'string', 'max:64', $unique('sku')],
            'barcode'               => ['nullable', 'string', 'max:64', $unique('barcode')],
            'category_id'           => ['nullable', 'integer', $inBusiness('item_categories')],
            'brand_id'              => ['nullable', 'integer', $inBusiness('item_brands')],
            'description'           => $text,
            'sale_price'            => [Rule::requiredIf($combo && $this->input('combo_pricing') === 'fixed'), ...$price],
            'sales_tax_id'          => ['nullable', 'integer', $taxFor(TaxRate::SALES)],
            'sales_description'     => $text,
            'purchase_price'        => $price,
            'purchase_tax_id'       => ['nullable', 'integer', $taxFor(TaxRate::PURCHASES)],
            'purchase_description'  => $text,
            'delivery_method'       => ['nullable', Rule::in(Item::DELIVERY_METHODS)],
            'delivery_instructions' => $text,
            'warranty_available'    => ['sometimes', 'boolean'],
            'warranty_period'       => [Rule::requiredIf($warranty), 'nullable', 'integer', 'min:1', 'max:1000'],
            'warranty_unit'         => [Rule::requiredIf($warranty), 'nullable', Rule::in(Item::WARRANTY_UNITS)],
            'warranty_start_rule'   => [Rule::requiredIf($warranty), 'nullable', Rule::in(Item::WARRANTY_START_RULES)],
            'warranty_terms'        => $text,
            'combo_pricing'         => [Rule::requiredIf($combo), 'nullable', Rule::in(Item::COMBO_PRICING)],
            'combo_display'         => [Rule::requiredIf($combo), 'nullable', Rule::in(Item::COMBO_DISPLAY)],
            'components'            => [Rule::requiredIf($combo), 'nullable', 'array', 'min:2', 'max:50'],
            'components.*.item_id'  => ['required', 'integer', 'distinct'],
            'components.*.quantity' => ['required', 'numeric', 'decimal:0,3', 'gt:0', 'max:999999999.999'],
            'is_active'             => ['sometimes', 'boolean'],
        ];
    }

    /**
     * What needs the database: a combo's components are active items of this
     * business that are sold, never a combo and never the combo itself. And an
     * item that is already part of a combo can neither become a combo nor
     * stop being sold.
     *
     * Only this business's rows are read. An id of another business's item is
     * answered with a 404 by the service, without a word about its combos.
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $edited = $this->route('id')
                ? app(ItemRepository::class)->findInWorkspace($this->attributes->get('workspace')?->id, (int) $this->route('id'))
                : null;

            if ($this->route('id') && ! $edited) {
                return;
            }

            $this->checkComponents($validator, $edited);
            $this->checkUseInCombos($validator, $edited);
            $this->checkTaxes($validator, $edited);
        }];
    }

    /**
     * A tax switched off since the item was given it may stay, so the rest of
     * the item can still be edited; a newly chosen one must be active.
     */
    private function checkTaxes(Validator $validator, ?Item $edited): void
    {
        foreach (['sales_tax_id', 'purchase_tax_id'] as $key) {
            $id = $this->input($key);

            if (! is_numeric($id) || $validator->errors()->has($key) || (int) $id === $edited?->{$key}) {
                continue;
            }

            $rate = app(TaxRateRepository::class)->findFor($this->attributes->get('workspace'), (int) $id);

            if ($rate && ! $rate->is_active) {
                $validator->errors()->add($key, "{$rate->name} is inactive. Choose an active tax.");
            }
        }
    }

    /**
     * A component that has been deactivated since it was added may stay, so
     * the rest of the combo can still be edited: it only stops the combo being
     * used in new sales (Doc 04 section 14). A new one must be active.
     */
    private function checkComponents(Validator $validator, ?Item $edited): void
    {
        $rows = $this->input('components');

        if ($this->input('type') !== Item::TYPE_COMBO || ! is_array($rows)) {
            return;
        }

        $ids   = collect($rows)->pluck('item_id')->filter(fn ($id) => is_numeric($id))->map(fn ($id) => (int) $id);
        $items = app(ItemRepository::class)->findManyInWorkspace($this->attributes->get('workspace')?->id, $ids)->keyBy('id');
        $kept  = $edited ? app(ItemRepository::class)->componentIdsOf($edited) : [];

        foreach ($rows as $index => $row) {
            $key = "components.{$index}.item_id";

            if (! is_array($row) || $validator->errors()->has($key)) {
                continue;
            }

            $component = $items->get((int) ($row['item_id'] ?? 0));

            $message = match (true) {
                $component === null                                              => 'Choose an item of this business.',
                $component->id === $edited?->id                                  => 'A combo cannot contain itself.',
                $component->isCombo()                                            => 'A combo cannot contain another combo.',
                ! $component->is_active && ! in_array($component->id, $kept, true) => "{$component->name} is inactive.",
                ! $component->isSold()                                           => "{$component->name} is only purchased, so it cannot be sold in a combo.",
                default                                                          => null,
            };

            if ($message) {
                $validator->errors()->add($key, $message);
            }
        }
    }

    private function checkUseInCombos(Validator $validator, ?Item $edited): void
    {
        if (! $edited || $validator->errors()->hasAny(['type', 'usage'])) {
            return;
        }

        $combo = app(ItemRepository::class)->firstComboNamedUsing($edited);

        if (! $combo) {
            return;
        }

        if ($this->input('type') === Item::TYPE_COMBO) {
            $validator->errors()->add('type', "This item is part of the combo \"{$combo->name}\", so it cannot be a combo itself.");
        } elseif ($this->input('usage') === Item::USAGE_PURCHASE) {
            $validator->errors()->add('usage', "This item is part of the combo \"{$combo->name}\", so it must stay sold.");
        }
    }

    public function attributes(): array
    {
        return [
            'sku'                   => 'SKU',
            'unit'                  => 'unit of measure',
            'category_id'           => 'category',
            'brand_id'              => 'brand',
            'sale_price'            => 'sales price',
            'sales_tax_id'          => 'sales tax',
            'purchase_tax_id'       => 'purchase tax',
            'warranty_period'       => 'warranty period',
            'components.*.item_id'  => 'component',
            'components.*.quantity' => 'quantity',
        ];
    }

    public function messages(): array
    {
        return [
            'type.in'                     => 'Choose physical product, digital product, service or combo.',
            'usage.in'                    => $this->input('type') === Item::TYPE_COMBO ? 'A combo can only be sold.' : 'Choose sales, purchase or both.',
            'sku.unique'                  => 'Another item of this business already has that SKU.',
            'barcode.unique'              => 'Another item of this business already has that barcode.',
            'category_id.exists'          => 'Choose one of this business\'s categories.',
            'brand_id.exists'             => 'Choose one of this business\'s brands.',
            'sales_tax_id.exists'         => 'Choose one of this business\'s sales taxes.',
            'purchase_tax_id.exists'      => 'Choose one of this business\'s purchase taxes.',
            'sale_price.required'         => 'A combo with its own price needs a sales price.',
            'components.required'         => 'A combo needs at least two components.',
            'components.min'              => 'A combo needs at least two components.',
            'components.*.item_id.distinct' => 'Each item can be a component once.',
            'components.*.quantity.gt'    => 'The quantity must be more than zero.',
            'decimal'                     => 'The :attribute has too many decimal places.',
        ];
    }
}
