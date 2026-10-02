<?php

namespace Tests\Feature;

use App\Models\Admin\Language;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LanguagesApiTest extends TestCase
{
    use RefreshDatabase;

    private function seedLanguages(): void
    {
        Language::create(['name' => 'English', 'code' => 'en', 'native_name' => 'English', 'is_default' => true, 'is_active' => true]);
        Language::create(['name' => 'Spanish', 'code' => 'es', 'native_name' => 'Español', 'is_default' => false, 'is_active' => true]);
        Language::create(['name' => 'French', 'code' => 'fr', 'native_name' => 'Français', 'is_default' => false, 'is_active' => false]);
        Language::create(['name' => 'Arabic', 'code' => 'ar', 'native_name' => 'العربية', 'is_default' => false, 'is_active' => true, 'direction' => 'rtl']);
    }

    public function test_list_returns_only_active_languages_default_first(): void
    {
        $this->seedLanguages();

        $response = $this->getJson('/api/v1/languages');

        $response->assertOk();

        // French is switched off, so it is gone; the default leads and the
        // rest follow by name.
        $this->assertSame(['en', 'ar', 'es'], array_column($response->json('data'), 'code'));
    }

    public function test_deactivating_a_language_removes_it_from_the_list(): void
    {
        $this->seedLanguages();

        Language::where('code', 'es')->update(['is_active' => false]);

        $codes = array_column($this->getJson('/api/v1/languages')->json('data'), 'code');

        $this->assertNotContains('es', $codes);
    }

    public function test_language_payload_has_the_expected_shape(): void
    {
        $this->seedLanguages();

        $this->getJson('/api/v1/languages')
            ->assertOk()
            ->assertJsonStructure(['data' => [['code', 'name', 'native_name']]])
            ->assertJsonPath('data.0.code', 'en')
            ->assertJsonPath('data.0.name', 'English')
            ->assertJsonPath('data.1.native_name', 'العربية');
    }

    public function test_empty_list_is_an_empty_array_not_an_error(): void
    {
        $this->getJson('/api/v1/languages')
            ->assertOk()
            ->assertExactJson(['data' => []]);
    }
}
