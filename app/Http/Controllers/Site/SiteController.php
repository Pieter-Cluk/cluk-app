<?php

namespace App\Http\Controllers\Site;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

/** Public website pages: the visitor picks a language (?lang=nl), kept for the visit. */
abstract class SiteController extends Controller
{
    protected function locale(Request $request): string
    {
        $codes = array_keys(config('cluk.locales'));
        $lang = $request->query('lang', session('site_lang', $request->getPreferredLanguage($codes) ?? 'en'));
        $lang = in_array($lang, $codes, true) ? $lang : 'en';
        session(['site_lang' => $lang]);
        app()->setLocale($lang);

        return $lang;
    }
}
