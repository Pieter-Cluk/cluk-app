<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\AuditLog;
use App\Models\Member;
use App\Services\Afrit6\RoomPlanner;
use App\Services\Afrit6\StatementBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

/** The member portal. Until CLUK approves the account, it shows where things stand. */
class PortalController extends Controller
{
    public function home(Request $request): View|RedirectResponse
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user()->load('company');
        if (! $member->isActive()) {
            return view('afrit6.status', ['member' => $member]);
        }
        if (! $member->hasCurrentTerms()) {
            return redirect()->route('afrit6.accept');
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

    /** Members accept the current house & booking rules and payment terms once (again after a new version). */
    public function showAccept(Request $request): View|RedirectResponse
    {
        $member = $request->user('member');

        return $member->hasCurrentTerms() ? redirect()->route('afrit6.portal') : view('afrit6.accept', ['member' => $member]);
    }

    public function accept(Request $request): RedirectResponse
    {
        $request->validate(['rules' => ['accepted'], 'payment_terms' => ['accepted']]);
        $request->user('member')->acceptCurrentTerms();

        return redirect()->route('afrit6.portal')->with('status', __('terms.accepted'));
    }

    /** The member asks for a door code (none yet, lost, or someone else knows it). Shows as a task for staff. */
    public function requestDoorCode(Request $request): RedirectResponse
    {
        /** @var Member $member */
        $member = Auth::guard('member')->user();
        $note = $request->validate(['note' => ['nullable', 'string', 'max:300']])['note'] ?? null;
        $member->forceFill(['door_requested_at' => now(), 'door_request_note' => $note])->save();
        AuditLog::record('afrit6.door_code_requested', $member, ['note' => $note]);

        return back()->with('status', __('workspace.portal.door_requested'));
    }
}
