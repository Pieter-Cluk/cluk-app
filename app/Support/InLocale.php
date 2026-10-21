<?php

namespace App\Support;

/** Run code (e.g. build an email) in another language, then switch back. */
class InLocale
{
    public static function run(?string $locale, callable $callback): mixed
    {
        $before = app()->getLocale();
        if ($locale && array_key_exists($locale, config('cluk.locales'))) {
            app()->setLocale($locale);
        }
        try {
            return $callback();
        } finally {
            app()->setLocale($before);
        }
    }
}
