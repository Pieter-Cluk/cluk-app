<?php

namespace Tests\Feature;

use Illuminate\Support\Arr;
use Illuminate\Support\Facades\App;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    public function test_every_language_has_the_same_translation_keys_as_english(): void
    {
        foreach (glob(lang_path('en/*.php')) as $file) {
            $name = basename($file);
            $english = array_keys(Arr::dot(require $file));

            foreach (array_keys(config('cluk.locales')) as $locale) {
                $this->assertFileExists(lang_path($locale.'/'.$name));
                $keys = array_keys(Arr::dot(require lang_path($locale.'/'.$name)));

                $this->assertEqualsCanonicalizing($english, $keys, "lang/{$locale}/{$name} is missing or has extra keys");
            }
        }
    }

    public function test_every_module_and_right_has_a_label(): void
    {
        foreach (config('cluk.modules') as $module) {
            $this->assertNotSame('cluk.modules.'.$module, __('cluk.modules.'.$module));
        }

        foreach (config('cluk.rights') as $right) {
            $this->assertNotSame('cluk.rights.'.$right, __('cluk.rights.'.$right));
        }
    }

    public function test_the_browser_language_is_used_when_supported(): void
    {
        $this->get('/login', ['Accept-Language' => 'fr-BE,fr;q=0.9,en;q=0.5'])->assertOk();

        $this->assertSame('fr', App::getLocale());
    }

    public function test_an_unsupported_browser_language_falls_back_to_the_default(): void
    {
        $this->get('/login', ['Accept-Language' => 'ja'])->assertOk();

        $this->assertSame(config('app.locale'), App::getLocale());
    }

    public function test_the_language_can_be_switched_for_the_session(): void
    {
        $this->post('/locale/nl')->assertRedirect();
        $this->assertSame('nl', session('locale'));

        $this->get('/login')->assertOk();
        $this->assertSame('nl', App::getLocale());
    }

    public function test_an_unknown_language_cannot_be_chosen(): void
    {
        $this->post('/locale/xx')->assertNotFound();
    }
}
