<?php

namespace Tests\Unit\I18n;

use App\Support\I18n\DateFormatGuard;
use PHPUnit\Framework\TestCase;

/**
 * PHP date() format strings are wrapped in t() by several views and must pass
 * through untranslated. The guard must recognise every format actually used
 * while never mistaking ordinary prose for one — the first version of this
 * guard classified almost any English sentence as a date because the date()
 * alphabet covers most lowercase letters.
 */
class DateFormatGuardTest extends TestCase
{
    /** Formats actually used via t() in this codebase. */
    public static function dateFormats(): array
    {
        return [
            ['M j, Y g:i A'],
            ['F d, Y'],
            ['d M Y H:i'],
            ['M j, Y'],
            ['F j, Y'],
            ['M Y'],
            ['Y-m-d'],
            ['M d, Y H:i'],
            ['g:i A'],
            ['D, M j, Y'],
            ['F j, Y g:i A'],
            ['Y/m/d'],
        ];
    }

    /** @dataProvider dateFormats */
    public function test_recognises_date_formats(string $format): void
    {
        $this->assertTrue(DateFormatGuard::isDateFormat($format), "Not detected as a date format: {$format}");
    }

    /** UI strings and prose that must never be treated as a date format. */
    public static function uiStrings(): array
    {
        return [
            ['Home'],
            ['Latest'],
            ['Read More'],
            ['Contact Us'],
            ['Your Name'],
            ['Filter'],
            ['Articles'],
            ['Get in touch'],
            ['Privacy Policy'],
            ['Sign Up'],
            ['FAQ'],
            ['SEO'],
            ['In-depth product reviews and analysis'],
            ['Loans, interest, mortgage, percentage, and GPA calculators.'],
            ['BMI, date calculations, and personal planning tools.'],
            ['This page explains how we collect, use, and protect your personal data.'],
            ['Personal Data:'],
            ['Browser type, IP address, pages visited, and time spent.'],
            ['To detect, prevent, and resolve technical issues.'],
            ['Important:'],
        ];
    }

    /** @dataProvider uiStrings */
    public function test_rejects_ui_strings_and_prose(string $text): void
    {
        $this->assertFalse(DateFormatGuard::isDateFormat($text), "Wrongly treated as a date format: {$text}");
    }

    public function test_handles_empty_and_punctuation_only_input(): void
    {
        $this->assertFalse(DateFormatGuard::isDateFormat(''));
        $this->assertFalse(DateFormatGuard::isDateFormat('   '));
        $this->assertFalse(DateFormatGuard::isDateFormat(','));
        $this->assertFalse(DateFormatGuard::isDateFormat(' - : '));
    }

    /**
     * Corpus guard: no translation key may be classified as a date format,
     * or that string would silently never be translated.
     */
    public function test_no_dictionary_key_is_mistaken_for_a_date_format(): void
    {
        $path = dirname(__DIR__, 3).'/resources/translations/bn.json';

        if (! is_file($path)) {
            $this->markTestSkipped('bn.json not present');
        }

        $keys = array_keys((array) json_decode((string) file_get_contents($path), true));

        $offenders = array_values(array_filter($keys, fn ($key) => DateFormatGuard::isDateFormat((string) $key)));

        $this->assertSame(
            [],
            $offenders,
            'Dictionary keys wrongly classified as date formats: '.implode(' | ', $offenders)
        );
    }
}
