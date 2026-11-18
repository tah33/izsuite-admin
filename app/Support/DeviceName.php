<?php

namespace App\Support;

/**
 * What a browser's User-Agent says it is, in a few words, for a user's list
 * of signed-in devices (Doc 12 section 22): "Chrome on Windows", "Safari on
 * iPhone" - null when it names nothing known. Each list is checked in order,
 * because agents name others too: Edge says Chrome, Chrome says Safari, an
 * iPhone says Mac OS X and Android says Linux.
 */
final class DeviceName
{
    private const BROWSERS = [
        'Edg/'            => 'Edge',
        'OPR/'            => 'Opera',
        'SamsungBrowser/' => 'Samsung Internet',
        'FxiOS/'          => 'Firefox',
        'Firefox/'        => 'Firefox',
        'CriOS/'          => 'Chrome',
        'EdgiOS/'         => 'Edge',
        'Chrome/'         => 'Chrome',
        'Safari/'         => 'Safari',
    ];

    private const SYSTEMS  = [
        'iPhone'   => 'iPhone',
        'iPad'     => 'iPad',
        'Android'  => 'Android',
        'Windows'  => 'Windows',
        'CrOS'     => 'ChromeOS',
        'Mac OS X' => 'macOS',
        'Linux'    => 'Linux',
    ];

    public static function from(?string $agent): ?string
    {
        if ($agent === null || $agent === '') {
            return null;
        }

        $browser = self::first(self::BROWSERS, $agent);
        $system  = self::first(self::SYSTEMS, $agent);

        return $browser && $system ? "{$browser} on {$system}" : ($browser ?? $system);
    }

    /** @param  array<string, string>  $names */
    private static function first(array $names, string $agent): ?string
    {
        foreach ($names as $needle => $name) {
            if (str_contains($agent, $needle)) {
                return $name;
            }
        }

        return null;
    }
}
