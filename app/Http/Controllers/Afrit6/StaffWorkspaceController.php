<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\Space;
use App\Models\Afrit6\Statement;
use App\Models\AuditLog;
use App\Models\Member;
use App\Services\Afrit6\Pricing;
use App\Services\Afrit6\StatementBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Staff side of the workspace: room week, desks plan, a member's desks, door code and statements. */
class StaffWorkspaceController extends Controller
{
    public function room(Request $request): View
    {
        $week = $request->query('week') ? Carbon::parse($request->query('week'))->startOfWeek() : today()->startOfWeek();
        $bookings = RoomBooking::live()->with('member.company')
            ->whereBetween('date', [$week->toDateString(), $week->copy()->addDays(4)->toDateString()])
            ->orderBy('date')->orderBy('start_slot')->get()
            ->groupBy(fn ($b) => $b->date->toDateString());

        return view('afrit6.staff.room', ['week' => $week, 'bookings' => $bookings]);
    }

    public function desks(Request $request): View
    {
        $day = $request->query('day') ? Carbon::parse($request->query('day'))->startOfDay() : today();
        $rentals = DeskRental::with('member.company')->activeOn($day)->get()->keyBy('space_id');
        $next = DeskRental::with('member')->where('starts_on', '>', $day->toDateString())->orderBy('starts_on')->get()->groupBy('space_id');

        return view('afrit6.staff.desks', [
            'day' => $day,
            'desks' => Space::desks()->get(),
            'rentals' => $rentals,
            'next' => $next,
        ]);
    }

    public function storeDesk(Request $request, Member $member): RedirectResponse
    {
        abort_unless($member->isActive(), 404);
        $data = $request->validate([
            'space_id' => ['required', 'integer', Rule::exists('afrit6_spaces', 'id')->where('kind', 'desk')],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'price' => ['required', 'numeric', 'min:0', 'max:100000'],
        ]);
        $space = Space::findOrFail($data['space_id']);
        if (! $space->isFree($data['starts_on'], $data['ends_on'] ?? null)) {
            throw ValidationException::withMessages(['space_id' => __('workspace.desks.not_free', ['desk' => $space->code])]);
        }
        $rental = DeskRental::create([
            'member_id' => $member->id, 'space_id' => $space->id, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'] ?? null,
            'monthly_price_cents' => (int) round($data['price'] * 100), 'created_by_id' => $request->user()->id,
        ]);
        AuditLog::record('afrit6.desk_added', $member, [
            'desk' => $space->code, 'from' => $rental->starts_on->toDateString(), 'to' => $rental->ends_on?->toDateString(), 'price' => Pricing::eur($rental->monthly_price_cents),
        ]);

        return back()->with('status', __('workspace.staff.desk_added', ['desk' => $space->code]));
    }

    public function endDesk(Request $request, Member $member, DeskRental $rental): RedirectResponse
    {
        abort_unless($rental->member_id === $member->id, 404);
        $data = $request->validate(['ends_on' => ['required', 'date', 'after_or_equal:'.$rental->starts_on->toDateString()]]);
        $before = $rental->ends_on?->toDateString();
        $rental->update(['ends_on' => $data['ends_on']]);
        AuditLog::record('afrit6.desk_ended', $member, ['desk' => $rental->space->code, 'from' => $before, 'to' => $rental->ends_on->toDateString()]);

        return back()->with('status', __('workspace.staff.desk_ended', ['desk' => $rental->space->code, 'date' => $rental->ends_on->format('d/m/Y')]));
    }

    /** Door code (Net2). Only people with the door code right; logged without the code itself. */
    public function doorCode(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate(['door_code' => ['nullable', 'string', 'regex:/^[0-9 ]{4,12}$/']]);
        $code = isset($data['door_code']) ? preg_replace('/\s+/', '', $data['door_code']) : null;
        $member->forceFill(['door_code' => $code ?: null])->save();
        AuditLog::record($code ? 'afrit6.door_code_set' : 'afrit6.door_code_removed', $member);

        return back()->with('status', __('workspace.staff.door_saved'));
    }

    /** Save the bill of a contract month as a proforma (for pre-invoicing). */
    public function storeStatement(Request $request, Member $member): RedirectResponse
    {
        $data = $request->validate(['day' => ['required', 'date']]);
        $builder = new StatementBuilder($member);
        $bill = $builder->build(Carbon::parse($data['day']));
        if (! $bill['lines']) {
            throw ValidationException::withMessages(['day' => __('workspace.staff.nothing_to_bill')]);
        }
        if (Statement::where('member_id', $member->id)->whereDate('period_start', $bill['start']->toDateString())->exists()) {
            throw ValidationException::withMessages(['day' => __('workspace.staff.statement_exists')]);
        }
        $statement = Statement::create([
            'member_id' => $member->id, 'company_id' => $member->company_id,
            'period_start' => $bill['start'], 'period_end' => $bill['end'],
            'lines' => $bill['lines'], 'total_cents' => $bill['total_cents'], 'created_by_id' => $request->user()->id,
        ]);
        AuditLog::record('afrit6.statement_created', $member, [
            'period' => $bill['start']->format('d/m/Y').' – '.$bill['end']->format('d/m/Y'), 'total' => Pricing::eur($statement->total_cents),
        ]);

        return back()->with('status', __('workspace.staff.statement_saved', ['total' => Pricing::eur($statement->total_cents)]));
    }
}
