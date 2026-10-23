<?php

namespace App\Http\Middleware;

use App\Services\Web\CustomerAuth;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Website pages for signed-in customers (account, baskets). Guests go to the website's sign-in page. */
class EnsureCustomer
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! CustomerAuth::current()) {
            if ($request->expectsJson()) {
                return response()->json(['message' => __('web.api.unauthenticated')], 401);
            }
            $request->session()->put('url.intended', $request->fullUrl());

            return redirect()->to(CustomerAuth::siteUrl('site.account.login', [], '/account/login'));
        }

        return $next($request);
    }
}
