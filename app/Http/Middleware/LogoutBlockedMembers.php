<?php

namespace App\Http\Middleware;

use App\Models\Member;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A member blocked by CLUK is signed out on their next click. */
class LogoutBlockedMembers
{
    public function handle(Request $request, Closure $next): Response
    {
        $member = Auth::guard('member')->user();

        if ($member !== null && $member->status === Member::BLOCKED) {
            Auth::guard('member')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('afrit6.login')->withErrors(['email' => __('afrit6.login.blocked')]);
        }

        return $next($request);
    }
}
