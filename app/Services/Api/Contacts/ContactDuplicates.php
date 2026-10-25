<?php

namespace App\Services\Api\Contacts;

use App\Models\User\Contact;
use App\Models\User\Workspace;
use App\Repositories\User\ContactRepository;
use Illuminate\Support\Arr;

/**
 * Finds the business's contacts that a contact being saved might be another
 * copy of (Doc 03 section 12), and says what each shares with it: its name
 * (the contact or trading name), a phone number, its e-mail or its tax ID.
 *
 * Only a warning: the same phone number or e-mail can belong to two contacts,
 * and saving one anyway is the user's call.
 *
 * Names and e-mails compare whole, ignoring case. A phone number compares by
 * its last nine digits - "+880 1711-000000" and "01711000000" are the same
 * number - and numbers shorter than seven digits are not compared at all.
 */
class ContactDuplicates
{
    /** The fields whose change can make a contact look like another. */
    public const FIELDS = ['name', 'trading_name', 'phone', 'alt_phone', 'email', 'tax_number'];

    private const MAX_SHOWN = 5;

    public function __construct(
        protected ContactRepository $contactRepository,
    ) {}

    /**
     * Up to five possible duplicates of a contact with $fields, leaving out
     * $except (the contact being edited): id, number, name, trading name and
     * what matched.
     *
     * @return list<array{id: int, contact_number: string, name: string, trading_name: ?string, matched_on: list<string>}>
     */
    public function find(Workspace $workspace, array $fields, ?Contact $except = null): array
    {
        $probe = $this->probe($fields);

        return $this->contactRepository->possibleDuplicatesOf($workspace, $probe, $except?->id)
            ->map(fn (Contact $contact) => [
                'id'             => $contact->id,
                'contact_number' => $contact->contact_number,
                'name'           => $contact->name,
                'trading_name'   => $contact->trading_name,
                'matched_on'     => $this->matches($contact, $probe),
            ])
            ->filter(fn (array $duplicate) => $duplicate['matched_on'])
            ->take(self::MAX_SHOWN)
            ->values()
            ->all();
    }

    /** What to compare, tidied the way the stored values are compared. */
    private function probe(array $fields): array
    {
        $names = array_values(array_unique(array_filter(array_map(
            fn ($name) => $this->tidyText($name),
            Arr::only($fields, ['name', 'trading_name']),
        ))));

        $phones = array_values(array_unique(array_filter(array_map(
            fn ($phone) => $this->phoneKey($phone),
            Arr::only($fields, ['phone', 'alt_phone']),
        ))));

        return [
            'names'  => $names,
            'email'  => $this->tidyText($fields['email'] ?? null),
            'phones' => $phones,
            'tax'    => $this->taxKey($fields['tax_number'] ?? null),
        ];
    }

    /** @return list<string> */
    private function matches(Contact $contact, array $probe): array
    {
        $matched = [];

        if (array_intersect($probe['names'], array_filter([$this->tidyText($contact->name), $this->tidyText($contact->trading_name)]))) {
            $matched[] = 'name';
        }

        $theirPhones = array_filter([$this->digits($contact->phone), $this->digits($contact->alt_phone)]);

        foreach ($probe['phones'] as $key) {
            foreach ($theirPhones as $digits) {
                if (str_ends_with($digits, $key)) {
                    $matched[] = 'phone';

                    break 2;
                }
            }
        }

        if ($probe['email'] && $probe['email'] === $this->tidyText($contact->email)) {
            $matched[] = 'email';
        }

        if ($probe['tax'] && $probe['tax'] === $this->taxKey($contact->tax_number)) {
            $matched[] = 'tax_id';
        }

        return $matched;
    }

    private function tidyText(?string $value): ?string
    {
        $value = mb_strtolower(trim((string) $value));

        return $value === '' ? null : $value;
    }

    private function digits(?string $phone): string
    {
        return preg_replace('/\D/', '', (string) $phone);
    }

    /** The last nine digits of a number, or all of a seven- or eight-digit one; null for shorter. */
    private function phoneKey(?string $phone): ?string
    {
        $digits = $this->digits($phone);

        if (strlen($digits) < 7) {
            return null;
        }

        return substr($digits, -9);
    }

    private function taxKey(?string $taxId): ?string
    {
        $key = mb_strtolower(str_replace([' ', '-', '.', '/'], '', trim((string) $taxId)));

        return $key === '' ? null : $key;
    }
}
