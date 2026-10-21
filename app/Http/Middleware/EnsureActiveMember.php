<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Desks, room and services are only for members CLUK has approved. */
class EnsureActiveMember
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user('member')?->isActive()) {
            return redirect()->route('afrit6.portal');
        }

        return $next($request);
    }
}
