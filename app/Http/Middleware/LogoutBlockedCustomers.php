<?php

namespace App\Http\Middleware;

use App\Models\Web\CustomerAccount;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/** A website login that staff blocked is signed out on its next click (a staff session in the same browser stays). */
class LogoutBlockedCustomers
{
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->hasSession() && $request->session()->has(Auth::guard('customer')->getName())) {
            $id = Auth::guard('customer')->id();
            if ($id && CustomerAccount::whereKey($id)->value('status') !== CustomerAccount::ACTIVE) {
                Auth::guard('customer')->logout();
            }
        }

        return $next($request);
    }
}
