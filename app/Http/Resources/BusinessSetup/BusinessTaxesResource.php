<?php

namespace App\Http\Resources\BusinessSetup;

use App\Http\Resources\Concerns\HidesValues;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Settings > Taxes: whether the business uses taxes, and its tax options - its
 * default sales, purchase and expense tax by id, null for none - whether it
 * uses withholding tax, and its tax registration: registered or not, the tax
 * country (a code) and up to two numbers, each null while not given.
 */
class BusinessTaxesResource extends JsonResource
{
    use HidesValues;

    /** What a user without View Tax Information does not see: the business's tax registration (Doc 12 section 14). */
    private const REGISTRATION = ['tax_registration_status', 'tax_country', 'tax_1_name', 'tax_1_no', 'tax_2_name', 'tax_2_no'];

    public function toArray(Request $request): array
    {
        return $this->hideWithout('taxes.view', [
            'tax_mode'                => $this->resource['tax_mode'],
            'price_tax_mode'          => $this->resource['price_tax_mode'],
            'tax_rounding'            => $this->resource['tax_rounding'],
            'default_sales_tax_id'    => $this->resource['default_sales_tax_id'],
            'default_purchase_tax_id' => $this->resource['default_purchase_tax_id'],
            'default_expense_tax_id'  => $this->resource['default_expense_tax_id'],
            'withholding_tax'         => $this->resource['withholding_tax'],
            'tax_registration_status' => $this->resource['tax_registration_status'],
            'tax_country'             => $this->resource['tax_country'],
            'tax_1_name'              => $this->resource['tax_1_name'],
            'tax_1_no'                => $this->resource['tax_1_no'],
            'tax_2_name'              => $this->resource['tax_2_name'],
            'tax_2_no'                => $this->resource['tax_2_no'],
        ], self::REGISTRATION);
    }
}
