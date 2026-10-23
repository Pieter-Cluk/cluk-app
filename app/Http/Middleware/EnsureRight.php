<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks an action unless the person has this right: 'right:finance.vat_treatment' (or one of several: 'right:help.manage|users.manage'). */
class EnsureRight
{
    public function handle(Request $request, Closure $next, string $right): Response
    {
        $u = $request->user();
        abort_unless($u && collect(explode('|', $right))->contains(fn ($r) => $u->hasRight($r)), 403);

        return $next($request);
    }
}
