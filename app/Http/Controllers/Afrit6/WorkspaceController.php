<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\Afrit6\DeskBooking;
use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\RequestMessage;
use App\Models\Afrit6\RoomBooking;
use App\Models\Afrit6\ServiceRequest;
use App\Models\Afrit6\Space;
use App\Models\AuditLog;
use App\Models\Member;
use App\Services\Afrit6\MemberMails;
use App\Services\Afrit6\Pricing;
use App\Services\Afrit6\RoomPlanner;
use App\Services\Afrit6\StatementBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The member portal of an approved member: desks, meeting room,
 * warehouse services and the running bill.
 */
class WorkspaceController extends Controller
{
    private function member(Request $request): Member
    {
        return $request->user('member');
    }

    // ---------------------------------------------------------------- pages

    public function desks(Request $request): View
    {
        $member = $this->member($request);
        $tomorrow = today()->addDay();
        $mine = $member->deskRentals()->current()->with('space')->get();
        $mineIds = $mine->pluck('space_id')->all();
        $takenIds = DeskRental::where('member_id', '!=', $member->id)
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $tomorrow->toDateString()))
            ->pluck('space_id')->all();

        $desks = Space::desks()->get()->map(fn (Space $s) => [
            'space' => $s,
            'state' => in_array($s->id, $mineIds, true) ? 'mine' : (in_array($s->id, $takenIds, true) ? 'taken' : 'free'),
        ]);

        // Book a free desk for a morning, an afternoon or a whole day.
        $dayDate = $this->nextWeekday($request->query('day') ? Carbon::parse($request->query('day'))->startOfDay() : today());
        $part = in_array($request->query('part'), DeskBooking::PARTS, true) ? $request->query('part') : 'day';
        if (! $request->query('day') && ! $request->query('part') && DeskBooking::startsAt($dayDate, 'day')->lte(now())) {
            // Nothing chosen yet: offer this afternoon if it has not started, else the next weekday.
            if (DeskBooking::startsAt($dayDate, 'pm')->gt(now())) {
                $part = 'pm';
            } else {
                $dayDate = $this->nextWeekday($dayDate->copy()->addDay());
            }
        }
        $office = Space::office();

        return view('afrit6.portal.desks', [
            'member' => $member,
            'mine' => $mine,
            'floors' => $desks->groupBy(fn ($d) => $d['space']->floor),
            'selected' => $desks->first(fn ($d) => $d['space']->code === $request->query('desk')),
            'dayDate' => $dayDate,
            'part' => $part,
            'partStarted' => DeskBooking::startsAt($dayDate, $part)->lte(now()),
            'freeForDay' => Space::desks()->get()->filter(fn (Space $s) => $s->isFreeForPart($dayDate, $part))->values(),
            'dayBookings' => DeskBooking::live()->with('space')->where('member_id', $member->id)
                ->where('date', '>=', today()->toDateString())->orderBy('date')->get(),
            'office' => $office,
            'officeRequest' => $office ? ServiceRequest::where('member_id', $member->id)->where('kind', 'office')->open()->first() : null,
            'officeMine' => $office ? $mine->firstWhere('space_id', $office->id) : null,
        ]);
    }

    private function nextWeekday(Carbon $d): Carbon
    {
        $d = $d->lt(today()) ? today() : $d->copy();
        while ($d->isWeekend()) {
            $d->addDay();
        }

        return $d;
    }

    /** Book a free desk for a morning, an afternoon or a whole day. */
    public function bookDay(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $data = $request->validate([
            'space_id' => ['required', 'integer', Rule::exists('afrit6_spaces', 'id')->where('kind', 'desk')->where('active', true)],
            'date' => ['required', 'date_format:Y-m-d', 'after_or_equal:today', 'before_or_equal:'.today()->addWeeks((int) config('afrit6.room.weeks_ahead'))->toDateString()],
            'part' => ['required', Rule::in(DeskBooking::PARTS)],
        ]);
        $date = Carbon::parse($data['date'])->startOfDay();
        if ($date->isWeekend()) {
            throw ValidationException::withMessages(['date' => __('workspace.room.err_weekend')]);
        }
        if (DeskBooking::startsAt($date, $data['part'])->lte(now())) {
            throw ValidationException::withMessages(['date' => __('workspace.room.err_past')]);
        }

        $booking = DB::transaction(function () use ($member, $date, $data) {
            $space = Space::whereKey($data['space_id'])->lockForUpdate()->first();
            if (! $space->isFreeForPart($date, $data['part'])) {
                throw ValidationException::withMessages(['space_id' => __('workspace.days.taken', ['desk' => $space->code])]);
            }

            return DeskBooking::create([
                'member_id' => $member->id, 'space_id' => $space->id, 'date' => $date,
                'part' => $data['part'], 'price_cents' => DeskBooking::price($data['part']),
            ]);
        });

        MemberMails::deskBooked($member, $booking);

        return redirect()->route('afrit6.portal.desks', ['day' => $date->toDateString(), 'part' => $data['part']])
            ->with('status', __('workspace.days.booked', [
                'desk' => $booking->space->code, 'date' => $date->locale(app()->getLocale())->translatedFormat('l j F'),
                'part' => __('workspace.days.parts.'.$booking->part), 'amount' => Pricing::eur($booking->price_cents),
            ]));
    }

    public function cancelDay(Request $request, DeskBooking $deskBooking): RedirectResponse
    {
        abort_unless($deskBooking->member_id === $this->member($request)->id && $deskBooking->cancelled_at === null, 404);
        if ($deskBooking->hasStarted()) {
            return back()->withErrors(['booking' => __('workspace.days.too_late')]);
        }
        $deskBooking->update(['cancelled_at' => now()]);
        MemberMails::deskCancelled($deskBooking->member, $deskBooking);

        return back()->with('status', __('workspace.days.cancelled'));
    }

    /** The private office is long term: the member asks for a price and the team gets in touch. */
    public function requestOffice(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $office = Space::office();
        abort_unless($office, 404);
        $data = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today'],
            'people' => ['required', 'integer', 'min:1', 'max:20'],
            'wishes' => ['nullable', 'string', 'max:2000'],
        ]);
        if (ServiceRequest::where('member_id', $member->id)->where('kind', 'office')->open()->exists()) {
            throw ValidationException::withMessages(['date' => __('workspace.office.already')]);
        }
        $sr = ServiceRequest::create([
            'member_id' => $member->id, 'kind' => 'office', 'status' => ServiceRequest::PENDING,
            'date' => $data['date'], 'qty' => $data['people'], 'space_id' => $office->id, 'note' => $data['wishes'] ?? null,
        ]);

        MemberMails::requestReceived($member, $sr);

        return redirect()->route('afrit6.portal.desks')->with('status', __('workspace.office.sent'));
    }

    public function room(Request $request): View
    {
        $member = $this->member($request);
        $planner = RoomPlanner::forRoom();
        abort_unless($planner, 404);

        $days = collect();
        for ($d = today()->copy(); $days->count() < 10; $d->addDay()) {
            if (! $d->isWeekend()) {
                $days->push($d->copy());
            }
        }
        $day = $request->query('day') ? Carbon::parse($request->query('day'))->startOfDay() : $days->first();
        if ($day->lt(today())) {
            $day = $days->first();
        }
        [$allowance, $used] = $planner->weekUsage($member, $day);

        return view('afrit6.portal.room', [
            'member' => $member,
            'days' => $days,
            'day' => $day,
            'plan' => $planner->dayPlan($day),
            'allowance' => $allowance,
            'used' => $used,
            'mine' => RoomBooking::live()->where('member_id', $member->id)->where('date', '>=', today()->toDateString())->orderBy('date')->orderBy('start_slot')->get(),
        ]);
    }

    public function services(Request $request): View
    {
        $member = $this->member($request);

        return view('afrit6.portal.services', [
            'member' => $member,
            'requests' => ServiceRequest::forMembers()->with(['messages.user', 'space', 'fromSpace'])
                ->where('member_id', $member->id)
                ->where(fn ($q) => $q->open()->orWhere('updated_at', '>=', now()->subDays(45)))
                ->orderByRaw("case when status in ('pending','waiting') then 0 when status = 'confirmed' then 1 else 2 end")
                ->orderByDesc('date')->get(),
            'open' => $request->query('new'),
        ]);
    }

    public function bill(Request $request): View
    {
        $member = $this->member($request);
        $builder = new StatementBuilder($member);

        return view('afrit6.portal.bill', [
            'member' => $member,
            'bill' => $builder->build(today(), app()->getLocale()),
            'statements' => $member->statements()->limit(12)->get(),
        ]);
    }

    // ---------------------------------------------------------------- desks

    /** Swap one of my desks for a free one, from tomorrow. Same price, same door code. */
    public function swapDesk(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $data = $request->validate([
            'from' => ['required', 'integer'],
            'to' => ['required', 'integer', Rule::exists('afrit6_spaces', 'id')->where('kind', 'desk')->where('active', true)],
        ]);
        $rental = $member->deskRentals()->current()->where('space_id', $data['from'])->firstOrFail();
        $to = Space::findOrFail($data['to']);
        $effective = $rental->starts_on->max(today()->addDay())->copy();

        if ($to->id === $rental->space_id || ! $to->isFree($effective, $rental->ends_on, $rental->id)) {
            throw ValidationException::withMessages(['to' => __('workspace.desks.not_free', ['desk' => $to->code])]);
        }

        DB::transaction(function () use ($member, $rental, $to, $effective) {
            $fromSpace = $rental->space;
            if ($rental->starts_on->gte($effective)) {
                $rental->update(['space_id' => $to->id]);
            } else {
                DeskRental::create([
                    'member_id' => $member->id, 'space_id' => $to->id, 'starts_on' => $effective,
                    'ends_on' => $rental->ends_on, 'monthly_price_cents' => $rental->monthly_price_cents,
                ]);
                $rental->update(['ends_on' => $effective->copy()->subDay()]);
            }
            ServiceRequest::create([
                'member_id' => $member->id, 'kind' => 'desk_swap', 'status' => ServiceRequest::CONFIRMED,
                'date' => $effective, 'space_id' => $to->id, 'from_space_id' => $fromSpace->id,
            ]);
            AuditLog::record('afrit6.desk_swapped', $member, ['from' => $fromSpace->code, 'to' => $to->code, 'date' => $effective->toDateString()]);
        });

        MemberMails::deskSwapped($member, Space::find($data['from'])?->code ?? '', $to->code, $effective);

        return redirect()->route('afrit6.portal.desks')->with('status', __('workspace.desks.swapped', [
            'to' => $to->code, 'date' => $effective->locale(app()->getLocale())->translatedFormat('j F'),
        ]));
    }

    /** Ask for an extra desk; the Afrit 6 team confirms it. */
    public function addDesk(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $data = $request->validate([
            'space_id' => ['required', 'integer', Rule::exists('afrit6_spaces', 'id')->where('kind', 'desk')->where('active', true)],
            'date' => ['required', 'date', 'after:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $space = Space::findOrFail($data['space_id']);
        $endsOn = $member->deskRentals()->current()->whereNotNull('ends_on')->max('ends_on');
        $hasOpenEnded = $member->deskRentals()->current()->whereNull('ends_on')->exists();
        $until = $hasOpenEnded || ! $endsOn ? null : $endsOn;

        if (! $space->isFree($data['date'], $until)) {
            throw ValidationException::withMessages(['space_id' => __('workspace.desks.not_free', ['desk' => $space->code])]);
        }
        $already = ServiceRequest::where('kind', 'desk_add')->where('space_id', $space->id)->open()->exists();
        if ($already) {
            throw ValidationException::withMessages(['space_id' => __('workspace.desks.already_asked', ['desk' => $space->code])]);
        }

        $sr = ServiceRequest::create([
            'member_id' => $member->id, 'kind' => 'desk_add', 'status' => ServiceRequest::PENDING,
            'date' => $data['date'], 'ends_on' => $until, 'space_id' => $space->id, 'note' => $data['note'] ?? null,
            'amount_cents' => Pricing::price('desk_month'),
        ]);

        MemberMails::requestReceived($member, $sr);

        return redirect()->route('afrit6.portal.desks')->with('status', __('workspace.desks.asked', ['desk' => $space->code]));
    }

    // ----------------------------------------------------------------- room

    public function book(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $planner = RoomPlanner::forRoom();
        abort_unless($planner, 404);
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'start' => ['required', 'integer', 'min:0'],
            'slots' => ['required', 'integer', 'min:1', 'max:'.config('afrit6.room.max_half_hours')],
            'setup' => ['required', Rule::in(array_keys(config('afrit6.room.setups')))],
            'note' => ['nullable', 'string', 'max:255'],
        ]);
        $date = Carbon::parse($data['date'])->startOfDay();

        $booking = DB::transaction(function () use ($planner, $member, $date, $data) {
            // Lock the room row so two members cannot take the same slot at once.
            Space::whereKey($planner->room->id)->lockForUpdate()->first();
            if ($error = $planner->check($date, (int) $data['start'], (int) $data['slots'], $data['setup'])) {
                throw ValidationException::withMessages(['start' => __('workspace.'.$error)]);
            }
            $booking = RoomBooking::create([
                'member_id' => $member->id, 'space_id' => $planner->room->id, 'date' => $date,
                'start_slot' => (int) $data['start'], 'slots' => (int) $data['slots'], 'setup' => $data['setup'],
                'note' => $data['note'] ?? null,
            ]);
            if ($booking->isClassroom()) {
                ServiceRequest::create([
                    'member_id' => $member->id, 'kind' => 'room_reset', 'status' => ServiceRequest::CONFIRMED,
                    'date' => $date, 'room_booking_id' => $booking->id,
                ]);
            }

            return $booking;
        });

        MemberMails::roomBooked($member, $booking);

        return redirect()->route('afrit6.portal.room', ['day' => $date->toDateString()])->with('status', __('workspace.room.booked', [
            'date' => $date->locale(app()->getLocale())->translatedFormat('l j F'), 'time' => $booking->timeRange(),
        ]));
    }

    public function cancelBooking(Request $request, RoomBooking $booking): RedirectResponse
    {
        abort_unless($booking->member_id === $this->member($request)->id && $booking->cancelled_at === null, 404);
        if ($booking->hasStarted()) {
            return back()->withErrors(['booking' => __('workspace.room.too_late')]);
        }
        DB::transaction(function () use ($booking) {
            $booking->update(['cancelled_at' => now()]);
            ServiceRequest::where('room_booking_id', $booking->id)->open()->delete();
        });

        MemberMails::roomCancelled($booking->member, $booking);

        return redirect()->route('afrit6.portal.room', ['day' => $booking->date->toDateString()])->with('status', __('workspace.room.cancelled'));
    }

    // ------------------------------------------------------------- services

    public function storeService(Request $request): RedirectResponse
    {
        $member = $this->member($request);
        $kind = $request->input('kind');
        $rules = [
            'kind' => ['required', Rule::in(['shelf', 'pallet', 'packing', 'receive', 'send'])],
            'date' => ['required', 'date', 'after_or_equal:today', 'before:'.today()->addYear()->toDateString()],
            'note' => ['nullable', 'string', 'max:1000'],
        ];
        if (in_array($kind, ['shelf', 'pallet'], true)) {
            $rules['qty'] = ['required', 'integer', 'min:1', 'max:50'];
        } else {
            $rules['colis'] = ['nullable', 'integer', 'min:0', 'max:999'];
            $rules['pallets'] = ['nullable', 'integer', 'min:0', 'max:99'];
            $rules['carrier'] = ['nullable', 'string', 'max:120'];
            $rules['from_text'] = ['nullable', 'string', 'max:160'];
            $rules['then_send'] = ['nullable', 'boolean'];
            $rules['receive_mode'] = [Rule::requiredIf($kind === 'receive'), 'nullable', Rule::in(['shelf24', 'store'])];
        }
        $data = $request->validate($rules);
        if (! in_array($kind, ['shelf', 'pallet'], true) && (int) ($data['colis'] ?? 0) + (int) ($data['pallets'] ?? 0) === 0) {
            throw ValidationException::withMessages(['colis' => __('workspace.services.need_count')]);
        }

        $attrs = [
            'member_id' => $member->id, 'kind' => $kind, 'status' => ServiceRequest::PENDING, 'date' => $data['date'],
            'qty' => (int) ($data['qty'] ?? 0), 'colis' => (int) ($data['colis'] ?? 0), 'pallets' => (int) ($data['pallets'] ?? 0),
            'receive_mode' => $kind === 'receive' ? $data['receive_mode'] : null,
            'then_send' => $kind === 'packing' && ! empty($data['then_send']),
            'from_text' => $data['from_text'] ?? null, 'carrier' => $data['carrier'] ?? null, 'note' => $data['note'] ?? null,
        ];
        $attrs['amount_cents'] = Pricing::oneOff($attrs);
        $sr = ServiceRequest::create($attrs);
        MemberMails::requestReceived($member, $sr);

        return redirect()->route('afrit6.portal.services')->with('status', __('workspace.services.sent'));
    }

    private function ownRequest(Request $request, ServiceRequest $sr): ServiceRequest
    {
        abort_unless($sr->member_id === $this->member($request)->id && in_array($sr->kind, config('afrit6.member_kinds'), true), 404);

        return $sr;
    }

    public function cancelService(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $sr = $this->ownRequest($request, $serviceRequest);
        if ($sr->status !== ServiceRequest::PENDING && ! ($sr->status === ServiceRequest::WAITING && $sr->status_before_wait === ServiceRequest::PENDING)) {
            return back()->withErrors(['request' => __('workspace.services.cannot_cancel')]);
        }
        MemberMails::requestCancelled($sr->member, $sr);
        $sr->delete();

        return back()->with('status', __('workspace.services.cancelled'));
    }

    /** Stop renting shelves or pallet places: today is the last day billed. */
    public function stopStorage(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $sr = $this->ownRequest($request, $serviceRequest);
        abort_unless($sr->isStorage() && $sr->status === ServiceRequest::CONFIRMED && ($sr->ends_on === null || $sr->ends_on->gt(today())), 404);
        $last = $sr->date->gt(today()) ? $sr->date : today();
        $sr->update(['ends_on' => $last]);
        $sr->messages()->create(['member_id' => $sr->member_id, 'body' => __('workspace.services.stop_note', ['date' => $last->format('d/m/Y')])]);
        MemberMails::storageStopped($sr->member, $sr);

        return back()->with('status', __('workspace.services.stopped', ['date' => $last->format('d/m/Y')]));
    }

    public function reply(Request $request, ServiceRequest $serviceRequest): RedirectResponse
    {
        $sr = $this->ownRequest($request, $serviceRequest);
        $data = $request->validate(['body' => ['required', 'string', 'max:2000']]);
        DB::transaction(function () use ($sr, $data) {
            RequestMessage::create(['request_id' => $sr->id, 'member_id' => $sr->member_id, 'body' => $data['body']]);
            if ($sr->status === ServiceRequest::WAITING) {
                $sr->update(['status' => $sr->status_before_wait ?: ServiceRequest::PENDING, 'status_before_wait' => null]);
            }
        });

        return back()->with('status', __('workspace.services.replied'));
    }
}
