<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\Member;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** The member portal. Until CLUK approves the account, it shows where things stand. */
class PortalController extends Controller
{
    public function home(Request $request): View
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user()->load('company');

        return view($member->isActive() ? 'afrit6.portal' : 'afrit6.status', ['member' => $member]);
    }
}
