<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Picks the language for this request: the signed-in person's own choice,
 * else the one chosen in this session, else the browser's preference,
 * else the app default.
 */
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        App::setLocale($this->resolve($request));

        return $next($request);
    }

    public function resolve(Request $request): string
    {
        $supported = array_keys(config('cluk.locales'));

        $candidates = [
            $request->user('web')?->locale,
            $request->user('member')?->locale,
            $request->hasSession() ? $request->session()->get('locale') : null,
            $request->getPreferredLanguage($supported),
        ];

        foreach ($candidates as $locale) {
            if (is_string($locale) && in_array($locale, $supported, true)) {
                return $locale;
            }
        }

        return config('app.locale');
    }
}
