<?php

namespace App\Enums;

/**
 * The two editions of an app family (Doc 01 section 9). Full replaces Lite and
 * keeps using the records Lite made.
 *
 * Compare by rank, so one check covers both cases: something that needs Lite
 * is met by Lite or Full, something that needs Full only by Full.
 */
enum Edition: string
{
    case Lite = 'lite';
    case Full = 'full';

    public function rank(): int
    {
        return match ($this) {
            self::Lite => 1,
            self::Full => 2,
        };
    }

    /** At least as high as $other. */
    public function covers(self $other): bool
    {
        return $this->rank() >= $other->rank();
    }

    public function label(): string
    {
        return match ($this) {
            self::Lite => 'Lite',
            self::Full => 'Full',
        };
    }

    /** For dropdowns: value => label. */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $edition) {
            $options[$edition->value] = $edition->label();
        }

        return $options;
    }
}
