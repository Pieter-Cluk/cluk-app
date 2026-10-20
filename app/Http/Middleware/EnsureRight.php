<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks an action unless the person has this right: 'right:finance.vat_treatment'. */
class EnsureRight
{
    public function handle(Request $request, Closure $next, string $right): Response
    {
        abort_unless($request->user()?->hasRight($right), 403);

        return $next($request);
    }
}
