<?php

namespace App\Models\User;

use App\Enums\DocumentType;
use App\Models\Concerns\HasFiles;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * How one type of a business's documents looks (Doc 06 section 14, Doc 11
 * section 20): Billing Starter has one template per type. It changes how a
 * document is shown, never what it says - its totals are the document's.
 *
 * Fields a document must show - the business and customer, the number and
 * date, the totals and any tax - are not among those that can be hidden; a
 * tax column only shows where tax applies. "Powered by izSuite" always shows
 * in Billing Starter. A signature or stamp image is the "signature" file.
 */
class DocumentTemplate extends Model
{
    use HasFiles;

    public const FONTS = ['sans', 'serif'];

    public const PAPER_SIZES = ['a4', 'letter'];

    public const HEADER_LAYOUTS = ['logo_left', 'logo_right', 'centered'];

    /** The foot of each page: text left, page number middle, "Powered by izSuite" right - or all three centred. */
    public const FOOTER_LAYOUTS = ['split', 'centered'];

    /** What can be shown or hidden besides what a document must show. */
    public const VISIBLE_FIELDS = ['business_contact', 'customer_contact', 'references', 'location', 'descriptions', 'warranty', 'components'];

    /** The line columns besides the item and its amount, which always show. */
    public const LINE_COLUMNS = ['quantity', 'unit_price', 'discount', 'tax'];

    /** PHP date formats: 28 Oct 2026, Oct 28, 2026, 28/10/2026, 10/28/2026, 2026-10-28. */
    public const DATE_FORMATS = ['d M Y', 'M j, Y', 'd/m/Y', 'm/d/Y', 'Y-m-d'];

    /** 1,234,567.89 - 1.234.567,89 - 1 234 567,89 - 12,34,567.89 (lakh). */
    public const NUMBER_FORMATS = ['comma_dot', 'dot_comma', 'space_comma', 'lakh'];

    /** The languages documents can be written in so far. */
    public const LANGUAGES = ['en'];

    protected $fillable = [
        'document_type',
        'color',
        'font',
        'paper_size',
        'header_layout',
        'show_logo',
        'visible_fields',
        'line_columns',
        'show_notes',
        'show_terms',
        'show_payment_instructions',
        'footer_text',
        'footer_layout',
        'language',
        'date_format',
        'number_format',
    ];

    protected $casts = [
        'document_type'             => DocumentType::class,
        'show_logo'                 => 'boolean',
        'visible_fields'            => 'array',
        'line_columns'              => 'array',
        'show_notes'                => 'boolean',
        'show_terms'                => 'boolean',
        'show_payment_instructions' => 'boolean',
    ];

    /** How a $type looks until the business changes it. */
    public static function defaultsFor(DocumentType $type): self
    {
        return new self([
            'document_type'             => $type,
            'color'                     => '#1d4ed8',
            'font'                      => 'sans',
            'paper_size'                => 'a4',
            'header_layout'             => 'logo_left',
            'show_logo'                 => true,
            'visible_fields'            => self::VISIBLE_FIELDS,
            'line_columns'              => self::LINE_COLUMNS,
            'show_notes'                => true,
            'show_terms'                => true,
            'show_payment_instructions' => true,
            'footer_text'               => null,
            'footer_layout'             => 'split',
            'language'                  => 'en',
            'date_format'               => 'd M Y',
            'number_format'             => 'comma_dot',
        ]);
    }

    /**
     * The settings as a document keeps them when it is numbered - its
     * template_snapshot - with the id of the signature image it showed.
     */
    public function settings(?int $signatureFileId): array
    {
        return [
            ...$this->only(['color', 'font', 'paper_size', 'header_layout', 'show_logo', 'visible_fields', 'line_columns', 'show_notes', 'show_terms', 'show_payment_instructions', 'footer_text', 'footer_layout', 'language', 'date_format', 'number_format']),
            'signature_file_id' => $signatureFileId,
        ];
    }

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
