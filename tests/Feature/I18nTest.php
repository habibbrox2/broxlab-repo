<?php

namespace Tests\Feature;

use App\Support\I18n\LanguageService;
use App\Support\I18n\Translator;
use Tests\TestCase;

/**
 * i18n integration tests for the rebuilt stack.
 *
 * The important regression here is cookie detection: LanguageService reads
 * $_COOKIE['brox_lang'] directly because request()->cookie() returned NULL
 * inside handled requests (the legacy-session middleware interferes with
 * Laravel's cookie resolution). These tests set $_COOKIE the way PHP itself
 * populates it for a real request.
 *
 * Rendering tests boot the full app (shared MySQL schema), matching the other
 * feature suites in this project.
 */
class I18nTest extends TestCase
{
    protected function tearDown(): void
    {
        unset($_COOKIE[LanguageService::LANG_COOKIE], $_SESSION);
        parent::tearDown();
    }

    // ── Translator ─────────────────────────────────────────────────────

    public function test_dictionary_translates_known_strings_to_bengali(): void
    {
        $translator = app(Translator::class);

        $this->assertSame('হোম', $translator->get('Home', 'bn'));
        $this->assertSame('লগইন', $translator->get('Login', 'bn'));
    }

    public function test_english_is_identity(): void
    {
        $translator = app(Translator::class);

        $this->assertSame('Home', $translator->get('Home', 'en'));
    }

    public function test_unknown_key_falls_back_to_the_source_string(): void
    {
        $translator = app(Translator::class);

        $untranslated = 'Definitely Not In The Dictionary 12345';

        $this->assertSame($untranslated, $translator->get($untranslated, 'bn'));
    }

    public function test_date_formats_are_never_translated(): void
    {
        $translator = app(Translator::class);

        foreach (['M j, Y g:i A', 'F d, Y', 'M Y', 'Y-m-d'] as $format) {
            $this->assertSame($format, $translator->get($format, 'bn'), "Date format was translated: {$format}");
        }
    }

    public function test_invalid_language_yields_an_empty_dictionary(): void
    {
        $translator = app(Translator::class);

        $this->assertSame([], $translator->dictionary('de'));
        $this->assertSame('Home', $translator->get('Home', 'de'));
    }

    // ── t() helper ─────────────────────────────────────────────────────

    public function test_t_helper_translates_when_bengali_is_active(): void
    {
        $_COOKIE[LanguageService::LANG_COOKIE] = 'bn';

        $this->assertSame('হোম', t('Home'));
    }

    public function test_t_helper_passes_date_formats_through(): void
    {
        $_COOKIE[LanguageService::LANG_COOKIE] = 'bn';

        $this->assertSame('M j, Y g:i A', t('M j, Y g:i A'));
    }

    public function test_t_helper_is_identity_for_english(): void
    {
        $this->assertSame('Home', t('Home'));
    }

    // ── Language detection ─────────────────────────────────────────────

    public function test_language_service_defaults_to_english(): void
    {
        $this->assertSame('en', app(LanguageService::class)->current());
    }

    public function test_lang_query_param_switches_to_bengali(): void
    {
        $this->get('/?lang=bn')->assertOk()->assertSee('data-lang="bn"', false);
    }

    public function test_brox_lang_cookie_switches_to_bengali(): void
    {
        $_COOKIE[LanguageService::LANG_COOKIE] = 'bn';

        $this->get('/')->assertOk()->assertSee('data-lang="bn"', false);
    }

    public function test_invalid_query_param_is_ignored(): void
    {
        $this->get('/?lang=de')->assertOk()->assertSee('data-lang="en"', false);
    }

    public function test_bengali_content_page_renders_translated_strings(): void
    {
        $this->get('/about-us?lang=bn')
            ->assertOk()
            ->assertSee('আমাদের গল্প', false);
    }

    public function test_english_content_page_renders_source_strings(): void
    {
        $this->get('/about-us')
            ->assertOk()
            ->assertSee('Our Story', false);
    }
}
