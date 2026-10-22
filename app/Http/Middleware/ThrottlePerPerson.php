<?php

namespace App\Http\Middleware;

use Illuminate\Routing\Middleware\ThrottleRequests;

/**
 * Like Laravel's throttle, but counted per person (staff or Afrit 6 member) and
 * per action. The default counts everyone without a staff login (members, the
 * public) on one shared counter per IP address for all limited pages, so people
 * in the same office blocked each other with "429 too many requests".
 */
class ThrottlePerPerson extends ThrottleRequests
{
    protected function resolveRequestSignature($request)
    {
        $who = ($u = $request->user('web')) ? 'u'.$u->getAuthIdentifier()
            : (($m = $request->user('member')) ? 'm'.$m->getAuthIdentifier() : 'ip'.$request->ip());
        $route = $request->route();
        $what = $route ? ($route->getName() ?? $route->uri()) : $request->path();

        return sha1($what.'|'.$who);
    }
}
