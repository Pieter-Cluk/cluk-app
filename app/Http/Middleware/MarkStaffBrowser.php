<?php

namespace App\Http\Middleware;

use App\Services\Website\Tracking;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Symfony\Component\HttpFoundation\Response;

/**
 * A browser where someone is signed in to the platform belongs to the team: it gets a mark that also counts on the
 * website (cookie on the shared domain), so the team's own website visits are left out of the website statistics.
 */
class MarkStaffBrowser
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if ($request->user('web') && $request->cookie(Tracking::STAFF) !== '1') {
            $response->headers->setCookie(Cookie::make(Tracking::STAFF, '1', 60 * 24 * 365 * 5, '/', Tracking::cookieDomain(), null, true, false, 'lax'));
        }

        return $response;
    }
}
