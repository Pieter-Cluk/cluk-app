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
        $member = $request->user('member');
        if (! $member?->isActive()) {
            return redirect()->route('afrit6.portal');
        }
        if (! $member->hasCurrentTerms()) {
            return redirect()->route('afrit6.accept');
        }

        return $next($request);
    }
}
