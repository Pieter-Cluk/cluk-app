<?php

namespace App\Http\Middleware;

use App\Support\Navigation;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks a screen unless the person may see its menu item. */
class EnsureNavAccess
{
    public function handle(Request $request, Closure $next, string $key): Response
    {
        $item = Navigation::item($key);

        abort_unless($item !== null && $request->user() && Navigation::canSee($item, $request->user()), 403);

        return $next($request);
    }
}
