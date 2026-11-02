<?php

namespace App\Enums;

/**
 * The kinds of document a business numbers (Doc 11 section 21), each with its
 * own run of numbers: Sales Lite's, Purchases Lite's and Expenses Lite's.
 */
enum DocumentType: string
{
    case Quotation = 'quotation';
    case Proforma = 'proforma';
    case Invoice = 'invoice';
    case CreditNote = 'credit_note';
    case CustomerReceipt = 'customer_receipt';
    case Purchase = 'purchase';
    case SupplierVoucher = 'supplier_voucher';
    case Expense = 'expense';
    case ExpenseVoucher = 'expense_voucher';

    public function label(): string
    {
        return match ($this) {
            self::Quotation       => 'Quotation',
            self::Proforma        => 'Proforma Invoice',
            self::Invoice         => 'Invoice',
            self::CreditNote      => 'Credit Note',
            self::CustomerReceipt => 'Customer Payment Receipt',
            self::Purchase        => 'Purchase',
            self::SupplierVoucher => 'Supplier Payment Voucher',
            self::Expense         => 'Expense',
            self::ExpenseVoucher  => 'Expense Payment Voucher',
        };
    }

    /** What they are called in a sentence: "Changed how invoices are numbered". */
    public function plural(): string
    {
        return match ($this) {
            self::Quotation       => 'quotations',
            self::Proforma        => 'proforma invoices',
            self::Invoice         => 'invoices',
            self::CreditNote      => 'credit notes',
            self::CustomerReceipt => 'customer payment receipts',
            self::Purchase        => 'purchases',
            self::SupplierVoucher => 'supplier payment vouchers',
            self::Expense         => 'expenses',
            self::ExpenseVoucher  => 'expense payment vouchers',
        };
    }

    /** The prefix a business starts with: QUO-00001, INV-00001, PUR-00001, SPV-00001, EXP-00001, EPV-00001. */
    public function defaultPrefix(): string
    {
        return match ($this) {
            self::Quotation       => 'QUO-',
            self::Proforma        => 'PRO-',
            self::Invoice         => 'INV-',
            self::CreditNote      => 'CN-',
            self::CustomerReceipt => 'RCT-',
            self::Purchase        => 'PUR-',
            self::SupplierVoucher => 'SPV-',
            self::Expense         => 'EXP-',
            self::ExpenseVoucher  => 'EPV-',
        };
    }

    /** The app family that makes it: its numbering shows while that app is on. */
    public function family(): string
    {
        return match ($this) {
            self::Purchase, self::SupplierVoucher => 'purchases',
            self::Expense, self::ExpenseVoucher   => 'expenses',
            default                               => 'sales',
        };
    }

    /** The sales documents Sales Lite makes, by the part of their address that names them: /sales/invoices. */
    public const KINDS = [
        'quotations'   => self::Quotation,
        'proformas'    => self::Proforma,
        'invoices'     => self::Invoice,
        'credit-notes' => self::CreditNote,
    ];

    /** The kinds the sales document form makes and changes. A credit note is made from its invoice instead. */
    public const FORM_KINDS = ['quotations', 'proformas', 'invoices'];

    public static function fromKind(string $kind): ?self
    {
        return self::KINDS[$kind] ?? null;
    }

    public function kind(): string
    {
        return array_search($this, self::KINDS, true) ?: $this->value;
    }

    /** The permissions its records come under (Doc 12): "sales.credit_notes" for a credit note, "purchases.payments" for a supplier's voucher. */
    public function permissionGroup(): string
    {
        return match ($this) {
            self::CustomerReceipt => 'sales.payments',
            self::Purchase        => 'purchases.purchases',
            self::SupplierVoucher => 'purchases.payments',
            self::Expense         => 'expenses.expenses',
            self::ExpenseVoucher  => 'expenses.payments',
            default               => 'sales.'.str_replace('-', '_', $this->kind()),
        };
    }

    /** A quotation or proforma: it has an expiry date, and is sent rather than issued. */
    public function isOffer(): bool
    {
        return $this === self::Quotation || $this === self::Proforma;
    }

    /** A proforma or invoice: customers pay from it, so it shows how (Doc 05 section 9). */
    public function isPaidFrom(): bool
    {
        return $this === self::Proforma || $this === self::Invoice;
    }
}
