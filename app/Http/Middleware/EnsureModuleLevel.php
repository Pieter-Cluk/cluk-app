<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Blocks an action unless the person has at least this level on the module: 'level:companies,2'. */
class EnsureModuleLevel
{
    public function handle(Request $request, Closure $next, string $module, string $level): Response
    {
        abort_unless($request->user()?->canUseModule($module, (int) $level), 403);

        return $next($request);
    }
}
