<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Member portal: a link with ?fill[...] (made by Clukie) opens a form already filled in. The values only go into the
 * form fields as "old input" for this one page view; the member checks them and confirms with the button.
 */
class MemberPrefill
{
    private const FIELDS = ['kind', 'date', 'time', 'names', 'company', 'on_screen', 'note', 'start', 'slots', 'setup',
        'qty', 'colis', 'pallets', 'from_text', 'receive_mode', 'carrier', 'people', 'wishes'];

    public function handle(Request $request, Closure $next): Response
    {
        $fill = $request->query('fill');
        if ($request->isMethod('GET') && is_array($fill) && $request->hasSession()) {
            $clean = collect($fill)->only(self::FIELDS)->filter(fn ($v) => is_scalar($v))->map(fn ($v) => mb_substr((string) $v, 0, 1000))->all();
            if ($clean) {
                $request->session()->now('_old_input', $clean);
                $request->attributes->set('prefilled', true);
            }
        }

        return $next($request);
    }
}
