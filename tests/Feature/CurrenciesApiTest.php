<?php

namespace Tests\Feature;

use App\Models\Admin\Currency;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrenciesApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedCurrencies(): void
    {
        Currency::create(['name' => 'US Dollar', 'code' => 'USD', 'symbol' => '$', 'is_default' => true, 'is_active' => true]);
        Currency::create(['name' => 'Bangladeshi Taka', 'code' => 'BDT', 'symbol' => '৳', 'is_default' => false, 'is_active' => true]);
        Currency::create(['name' => 'Euro', 'code' => 'EUR', 'symbol' => '€', 'is_default' => false, 'is_active' => false]);
        Currency::create(['name' => 'British Pound', 'code' => 'GBP', 'symbol' => '£', 'is_default' => false, 'is_active' => true]);
    }

    public function test_list_returns_only_active_currencies_default_first(): void
    {
        $this->seedCurrencies();

        $response = $this->getJson('/api/v1/currencies');

        $response->assertOk();

        // Euro is switched off, so it is gone; the default leads and the rest
        // follow by name.
        $this->assertSame(['USD', 'BDT', 'GBP'], array_column($response->json('data'), 'code'));
    }

    public function test_deactivating_a_currency_removes_it_from_the_list(): void
    {
        $this->seedCurrencies();

        Currency::where('code', 'BDT')->update(['is_active' => false]);

        $codes = array_column($this->getJson('/api/v1/currencies')->json('data'), 'code');

        $this->assertNotContains('BDT', $codes);
    }

    public function test_currency_payload_has_the_expected_shape(): void
    {
        $this->seedCurrencies();

        $this->getJson('/api/v1/currencies')
            ->assertOk()
            ->assertJsonStructure(['data' => [['code', 'name', 'symbol']]])
            ->assertJsonPath('data.0.code', 'USD')
            ->assertJsonPath('data.0.name', 'US Dollar')
            ->assertJsonPath('data.0.symbol', '$');
    }

    public function test_empty_list_is_an_empty_array_not_an_error(): void
    {
        $this->getJson('/api/v1/currencies')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
