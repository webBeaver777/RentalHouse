<?php

namespace Tests\Feature\Localization;

use App\Modules\Localization\Infrastructure\Models\Locale;
use Database\Seeders\LocaleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(LocaleSeeder::class);
    }

    /**
     * There should be exactly one default locale.
     */
    public function test_exactly_one_default_locale_exists(): void
    {
        $defaultCount = Locale::where('is_default', true)->count();

        $this->assertEquals(1, $defaultCount, 'There should be exactly one default locale');
    }

    /**
     * Default locale should be Polish (pl).
     */
    public function test_default_locale_is_polish(): void
    {
        $defaultLocale = Locale::getDefault();

        $this->assertNotNull($defaultLocale);
        $this->assertEquals('pl', $defaultLocale->code);
        $this->assertEquals('Polski', $defaultLocale->native_name);
    }

    /**
     * Locale can be created.
     */
    public function test_locale_can_be_created(): void
    {
        $locale = Locale::create([
            'code' => 'de',
            'name' => 'German',
            'native_name' => 'Deutsch',
            'is_default' => false,
            'is_active' => true,
        ]);

        $this->assertDatabaseHas('locales', [
            'code' => 'de',
            'native_name' => 'Deutsch',
        ]);
    }

    /**
     * Setting new default unsets previous default.
     */
    public function test_setting_new_default_unsets_previous(): void
    {
        $english = Locale::where('code', 'en')->first();
        $english->setAsDefault();

        $this->assertTrue($english->fresh()->is_default);
        $this->assertFalse(Locale::where('code', 'pl')->first()->is_default);

        // Only one default should exist
        $this->assertEquals(1, Locale::where('is_default', true)->count());
    }

    /**
     * Polish and English locales are seeded.
     */
    public function test_polish_and_english_locales_are_seeded(): void
    {
        $this->assertDatabaseHas('locales', ['code' => 'pl']);
        $this->assertDatabaseHas('locales', ['code' => 'en']);
    }
}
