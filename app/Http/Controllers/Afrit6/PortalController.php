<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\Member;
use App\Services\Afrit6\RoomPlanner;
use App\Services\Afrit6\StatementBuilder;
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
        if (! $member->isActive()) {
            return view('afrit6.status', ['member' => $member]);
        }

        $planner = RoomPlanner::forRoom();
        [$allowance, $used] = $planner ? $planner->weekUsage($member, today()) : [0, 0];
        $open = ServiceRequest::forMembers()->where('member_id', $member->id)->open()->orderBy('date')->get();

        return view('afrit6.portal', [
            'member' => $member,
            'desks' => $member->desksToday(),
            'allowance' => $allowance,
            'used' => $used,
            'bookings' => RoomBooking::live()->where('member_id', $member->id)->where('date', '>=', today()->toDateString())->orderBy('date')->orderBy('start_slot')->limit(3)->get(),
            'open' => $open->take(5),
            'waiting' => $open->where('status', ServiceRequest::WAITING),
            'bill' => (new StatementBuilder($member))->build(today(), app()->getLocale()),
        ]);
    }
}
