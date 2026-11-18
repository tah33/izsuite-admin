<?php

namespace App\Support;

/**
 * How a country writes an address (Doc 15 section 15.19 - Phase F3a): its
 * lines, each naming the parts it holds in braces - {line1}, {line2},
 * {city}, {region}, {postal} and {country} - as "{city}, {region} {postal}".
 * A document prints an address this way, by the address's own country.
 *
 * A part left empty takes its separator with it, and a line whose parts are
 * all empty goes.
 */
final class AddressFormat
{
    public const PARTS = ['line1', 'line2', 'city', 'region', 'postal', 'country'];

    /** Each part on its own line: how every address was printed before Phase F3a. */
    public const DEFAULT = "{line1}\n{line2}\n{city}\n{region}\n{postal}\n{country}";

    /** An address to show a format with. */
    public const SAMPLE = ['line1' => 'House 12, Road 5', 'line2' => 'Block C', 'city' => 'Springfield', 'region' => 'Central', 'postal' => '1207', 'country' => 'Country'];

    /** Where a country writes its city, region and postal code on one line, as the countries table started (migration 000145). */
    private const BUILT_IN = [
        'US' => "{line1}\n{line2}\n{city}, {region} {postal}\n{country}",
        'CA' => "{line1}\n{line2}\n{city} {region} {postal}\n{country}",
        'AU' => "{line1}\n{line2}\n{city} {region} {postal}\n{country}",
    ];

    public static function builtIn(string $country): string
    {
        return self::BUILT_IN[$country] ?? self::DEFAULT;
    }

    /**
     * $parts - by the names above, the country by its name - as the lines of
     * $format (the default when it has none).
     *
     * @param  array<string, ?string>  $parts
     * @return list<string>
     */
    public static function lines(?string $format, array $parts): array
    {
        $lines = [];

        foreach (explode("\n", str_replace("\r", '', $format ?: self::DEFAULT)) as $line) {
            $filled = false;
            $text   = preg_replace_callback('/\{([a-z0-9]+)\}/', function (array $match) use ($parts, &$filled) {
                $value  = trim((string) ($parts[$match[1]] ?? ''));
                $filled = $filled || $value !== '';

                return $value;
            }, $line);

            if (! $filled) {
                continue;
            }

            // Spaces an empty part doubled, a separator it doubled, and one it left at an end.
            $text = preg_replace('/[ \t]+/', ' ', $text);
            $text = preg_replace('/([,;])\s*(?=[,;])/', '', $text);
            $text = preg_replace('/^\s*[-–]\s+|\s+[-–]\s*$/u', '', $text);
            $text = trim($text, " \t,;");

            if ($text !== '') {
                $lines[] = $text;
            }
        }

        return $lines;
    }

    /** The parts $format names that are not one of PARTS. @return list<string> */
    public static function unknownParts(string $format): array
    {
        preg_match_all('/\{([^}]*)\}/', $format, $matches);

        return array_values(array_diff(array_unique($matches[1]), self::PARTS));
    }
}
