<?php

namespace Tests\Feature;

use App\Support\Countries;
use Tests\TestCase;

/**
 * GET /countries: the list every country field picks from - public, like the
 * currency and language lists.
 */
class CountriesApiTest extends TestCase
{
    public function test_every_country_is_listed_by_name_with_its_calling_code_and_address_labels(): void
    {
        $countries = $this->getJson('/api/v1/countries')->assertOk()->json('data');

        $byCode = array_column($countries, null, 'code');

        $this->assertSame(
            ['code' => 'BD', 'name' => 'Bangladesh', 'calling_code' => '880', 'region_label' => 'State / Region', 'postal_label' => 'Postal Code'],
            $byCode['BD'],
        );
        $this->assertSame(['State', 'ZIP Code'], [$byCode['US']['region_label'], $byCode['US']['postal_label']]);
        $this->assertSame(['County', 'Postcode'], [$byCode['GB']['region_label'], $byCode['GB']['postal_label']]);
        $this->assertNull($byCode['BV']['calling_code']);
        $this->assertCount(count(Countries::codes()), $countries);

        // Alphabetical by name, an accented name among its letter.
        $names = array_column($countries, 'name');
        $this->assertSame('Afghanistan', $names[0]);
        $this->assertLessThan(array_search('Albania', $names), array_search('Åland Islands', $names));
    }

    public function test_a_typed_country_name_is_turned_into_its_code(): void
    {
        $this->assertSame('BD', Countries::codeFor(' bangladesh '));
        $this->assertSame('US', Countries::codeFor('USA'));
        $this->assertSame('GB', Countries::codeFor('gb'));
        $this->assertNull(Countries::codeFor('Atlantis'));
        $this->assertNull(Countries::codeFor(''));
    }
}
