<?php

namespace App\Services\Api\Contacts;

use App\Models\User\Contact;

/**
 * What merging one contact into another changes on the one kept (Doc 17
 * Phase C3): only what it lacks, so nothing it holds is overwritten.
 *
 * - Each detail it has none of: a trading name, a person and job title, an
 *   e-mail, a phone, a website, a language, default currencies and payment
 *   terms, and tax information. An alternative phone it lacks takes the
 *   other's number it does not have.
 * - Its address, billing and shipping together, when it has none - a
 *   country alone is not one. Two addresses are never mixed.
 * - Its role widens to both when one is a customer and the other a supplier.
 * - Its tags gain the other's, up to the twenty a contact can have.
 * - Its notes gain the other's, under a line naming it, while they fit.
 *
 * Its type, its name and its credit control stay its own.
 */
final class ContactMergeFields
{
    /** Taken one by one, each when the kept contact has none. */
    public const DETAILS = [
        'trading_name', 'contact_person', 'job_title', 'email', 'phone', 'website', 'preferred_language',
        'sales_currency', 'customer_payment_terms', 'purchase_currency', 'supplier_payment_terms',
        'tax_number', 'tax_country', 'tax_treatment', 'tax_exemption_number',
    ];

    /** What makes an address: without any of them, a contact has none. */
    public const ADDRESS_LINES = ['billing_line1', 'billing_line2', 'billing_city', 'billing_state', 'billing_postal_code'];

    /** Taken together, when the kept contact has no address. */
    public const ADDRESS = [...self::ADDRESS_LINES, 'billing_country', 'shipping_same_as_billing', ...Contact::SHIPPING_FIELDS];

    /** As many as a contact can be saved with (see SaveContactRequest). */
    private const MAX_TAGS = 20;

    private const MAX_NOTES = 5000;

    /**
     * The changes, column => value, and what becomes of $merged's notes:
     * "filled" ($kept had none), "added" (after $kept's own), "kept" (they
     * would not fit, so they stay on $merged only) - or null, with none.
     *
     * @return array{changes: array<string, mixed>, notes: ?string}
     */
    public static function of(Contact $kept, Contact $merged): array
    {
        $changes = [];

        foreach (self::DETAILS as $field) {
            if (self::blank($kept->{$field}) && ! self::blank($merged->{$field})) {
                $changes[$field] = $merged->{$field};
            }
        }

        if (self::blank($kept->alt_phone) && ($number = self::otherNumber($merged, $changes['phone'] ?? $kept->phone))) {
            $changes['alt_phone'] = $number;
        }

        if (! self::hasAddress($kept) && self::hasAddress($merged)) {
            foreach (self::ADDRESS as $field) {
                $changes[$field] = $merged->{$field};
            }
        }

        $role = $kept->role === $merged->role ? $kept->role : Contact::ROLE_BOTH;

        if ($role !== $kept->role) {
            $changes['role'] = $role;
        }

        if (($tags = self::tags($kept->tags ?? [], $merged->tags ?? [])) !== ($kept->tags ?? [])) {
            $changes['tags'] = $tags;
        }

        [$notes, $outcome] = self::notes($kept, $merged);

        if ($notes !== null) {
            $changes['notes'] = $notes;
        }

        return ['changes' => $changes, 'notes' => $outcome];
    }

    /** $merged's first number that is not $phone, for an alternative phone. */
    private static function otherNumber(Contact $merged, ?string $phone): ?string
    {
        foreach ([$merged->phone, $merged->alt_phone] as $number) {
            if (! self::blank($number) && ! self::sameNumber($number, $phone)) {
                return $number;
            }
        }

        return null;
    }

    /** By their last nine digits, as possible duplicates are found (see ContactDuplicates). */
    private static function sameNumber(string $number, ?string $other): bool
    {
        $digits = fn (?string $phone) => substr(preg_replace('/\D/', '', (string) $phone), -9);

        return $other !== null && $digits($number) !== '' && $digits($number) === $digits($other);
    }

    private static function hasAddress(Contact $contact): bool
    {
        foreach (self::ADDRESS_LINES as $field) {
            if (! self::blank($contact->{$field})) {
                return true;
            }
        }

        return false;
    }

    /** $tags, then those of $more it does not have - whatever their case - while there is room. */
    private static function tags(array $tags, array $more): array
    {
        $held = array_map('mb_strtolower', $tags);

        foreach ($more as $tag) {
            if (count($tags) >= self::MAX_TAGS) {
                break;
            }

            if (! in_array(mb_strtolower($tag), $held, true)) {
                $tags[]  = $tag;
                $held[] = mb_strtolower($tag);
            }
        }

        return $tags;
    }

    /** @return array{0: ?string, 1: ?string} The notes to save, if they change, and what became of $merged's. */
    private static function notes(Contact $kept, Contact $merged): array
    {
        if (self::blank($merged->notes)) {
            return [null, null];
        }

        if (self::blank($kept->notes)) {
            return [$merged->notes, 'filled'];
        }

        $notes = rtrim($kept->notes)."\n\nMerged from {$merged->contact_number} {$merged->name}:\n".trim($merged->notes);

        return mb_strlen($notes) <= self::MAX_NOTES ? [$notes, 'added'] : [null, 'kept'];
    }

    private static function blank(mixed $value): bool
    {
        return $value === null || (is_string($value) && trim($value) === '');
    }
}
