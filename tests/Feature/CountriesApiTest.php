<?php

namespace Tests\Feature;

use App\Support\Countries;
use App\Support\CountryDirectory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * GET /countries: the list every country field picks from - public, like the
 * currency and language lists - from the countries table (Doc 15 Phase F3a).
 */
class CountriesApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_country_is_listed_by_name_with_its_calling_code_address_labels_and_time_zones(): void
    {
        $countries = $this->getJson('/api/v1/countries')->assertOk()->json('data');

        $byCode = array_column($countries, null, 'code');

        $this->assertSame(
            ['code' => 'BD', 'name' => 'Bangladesh', 'calling_code' => '880', 'region_label' => 'State / Region', 'postal_label' => 'Postal Code', 'timezone' => 'Asia/Dhaka', 'timezones' => ['Asia/Dhaka']],
            $byCode['BD'],
        );
        $this->assertSame(['State', 'ZIP Code', 'America/New_York'], [$byCode['US']['region_label'], $byCode['US']['postal_label'], $byCode['US']['timezone']]);
        $this->assertContains('America/Los_Angeles', $byCode['US']['timezones']);
        $this->assertSame(['County', 'Postcode'], [$byCode['GB']['region_label'], $byCode['GB']['postal_label']]);
        $this->assertNull($byCode['BV']['calling_code']);
        $this->assertNull($byCode['BV']['timezone']);
        $this->assertCount(count(Countries::codes()), $countries);
        $this->assertCount(250, $countries);

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

    public function test_before_the_table_exists_the_built_in_list_is_read(): void
    {
        Schema::drop('country_versions');
        Schema::drop('countries');
        app(CountryDirectory::class)->forget();

        $this->assertSame('Bangladesh', Countries::name('BD'));
        $this->assertSame('BD', Countries::codeFor('Bangladesh'));
        $this->assertCount(250, Countries::codes());
    }
}
