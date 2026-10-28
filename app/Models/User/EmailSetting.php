<?php

namespace App\Models\User;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Settings > Email (Doc 11 sections 15 and 22): the name a business's emails
 * are sent under, where replies go, their language, and the emails each
 * document - and the manual payment reminder - starts with. Empty, the sender
 * is the business's name and replies go to its owner. The emails go through
 * the platform's mail server.
 *
 * A template's subject and body take the placeholders in PLACEHOLDERS,
 * filled in as each email is written.
 */
class EmailSetting extends Model
{
    /** What a template can say, and what it stands for. */
    public const PLACEHOLDERS = ['business_name', 'contact_name', 'document_number', 'document_date', 'due_date', 'amount', 'balance', 'document_link'];

    /** The email each kind starts with until the business writes its own. */
    public const DEFAULT_TEMPLATES = [
        'quotation' => [
            'subject' => 'Quotation {document_number} from {business_name}',
            'body'    => "Dear {contact_name},\n\nPlease find our quotation {document_number} for {amount}.\n\nWe look forward to hearing from you.\n\n{business_name}",
        ],
        'proforma' => [
            'subject' => 'Proforma invoice {document_number} from {business_name}',
            'body'    => "Dear {contact_name},\n\nPlease find proforma invoice {document_number} for {amount}. It becomes an invoice once paid.\n\n{business_name}",
        ],
        'invoice' => [
            'subject' => 'Invoice {document_number} from {business_name}',
            'body'    => "Dear {contact_name},\n\nPlease find invoice {document_number} for {amount}, due on {due_date}.\n\nThank you for your business.\n\n{business_name}",
        ],
        'credit_note' => [
            'subject' => 'Credit note {document_number} from {business_name}',
            'body'    => "Dear {contact_name},\n\nPlease find credit note {document_number} for {amount}.\n\n{business_name}",
        ],
        'customer_receipt' => [
            'subject' => 'Receipt {document_number} from {business_name}',
            'body'    => "Dear {contact_name},\n\nThank you for your payment of {amount}. Your receipt {document_number} is attached.\n\n{business_name}",
        ],
        'reminder' => [
            'subject' => 'Payment reminder: invoice {document_number}',
            'body'    => "Dear {contact_name},\n\nThis is a reminder that invoice {document_number}, due on {due_date}, has {balance} still to pay.\n\nIf you have paid it already, please ignore this message.\n\n{business_name}",
        ],
    ];

    protected $fillable = [
        'sender_name',
        'reply_to',
        'language',
        'templates',
    ];

    protected $attributes = [
        'language' => 'en',
    ];

    protected $casts = [
        'templates' => 'array',
    ];

    /** The subject and body of $key's email: the business's own, or the default. */
    public function template(string $key): array
    {
        return [...self::DEFAULT_TEMPLATES[$key], ...array_filter($this->templates[$key] ?? [])];
    }

    // ── Relationships ──

    public function workspace(): BelongsTo
    {
        return $this->belongsTo(Workspace::class);
    }
}
