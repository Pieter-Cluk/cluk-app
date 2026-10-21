<?php

namespace App\Http\Controllers\Factory;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyDetail;
use App\Models\Factory\Booking;
use App\Models\Factory\Task;
use App\Services\Factory\Bookings;
use App\Services\Factory\Tasks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Cluk Factory for staff: requests to answer, the hall calendar, bookings and their checklist. */
class FactoryController extends Controller
{
    public function index(Request $request): View
    {
        $month = preg_match('/^\d{4}-\d{2}$/', (string) $request->query('m')) ? Carbon::parse($request->query('m').'-01') : today()->startOfMonth();
        $from = $month->copy()->startOfMonth();
        $until = $month->copy()->endOfMonth();
        $occ = Booking::occupancy($from, $until);
        $used = collect($occ)->filter(fn ($o) => $o['booking']->status !== 'option')->count();

        return view('factory.index', [
            'month' => $month,
            'occ' => $occ,
            'requests' => Booking::where('status', 'request')->orderBy('created_at')->get(),
            'options' => Booking::where('status', 'option')->orderBy('option_until')->get(),
            'bookings' => Booking::with(['company', 'owner'])->whereIn('status', ['option', 'confirmed', 'done'])
                ->whereDate('start_date', '>=', $from->copy()->subDays(3))->whereDate('start_date', '<=', $until)->orderBy('start_date')->get(),
            'tiles' => [
                'requests' => Booking::where('status', 'request')->count(),
                'options' => Booking::where('status', 'option')->where('option_until', '>', now())->count(),
                'events' => Booking::whereIn('status', ['confirmed', 'done'])->whereBetween('start_date', [$from, $until])->count(),
                'usage' => $until->day ? (int) round($used / $until->day * 100) : 0,
                'used' => $used, 'days' => $until->day,
            ],
            'tasks' => Task::open()->with(['booking', 'assignee'])->where('due_at', '<=', now()->addDays(14))
                ->whereHas('booking', fn ($q) => $q->where('status', 'confirmed'))
                ->when($request->query('tasks') === 'mine', fn ($q) => $q->where('assignee_id', $request->user()->id))
                ->orderBy('due_at')->limit(60)->get(),
            'taskFilter' => $request->query('tasks') === 'mine' ? 'mine' : 'all',
            'canWork' => $request->user()->canUseModule('factory', 2),
        ]);
    }

    public function show(Request $request, Booking $booking): View
    {
        Tasks::sync($booking);
        $booking->load(['company', 'contact', 'deal', 'quote', 'owner', 'gearQuote.order', 'tasks.assignee', 'tasks.doneBy']);
        $me = $request->user();

        return view('factory.show', [
            'b' => $booking, 'canWork' => $me->canUseModule('factory', 2), 'clashes' => $booking->clashes(),
            'crew' => FactoryTaskController::crew()->filter(fn ($u) => $me->canHandTaskTo($u))->values(),
        ]);
    }

    public function create(): View
    {
        return view('factory.form', ['b' => new Booking(['event_type' => 'corporate', 'days' => 1, 'size' => 'M', 'level' => 'basic', 'guests' => 100, 'language' => 'nl', 'start_date' => today()->addWeeks(4), 'load_in_time' => '08:00', 'doors_time' => '18:00', 'end_time' => '01:00'])]);
    }

    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:200'],
            'event_type' => ['required', Rule::in(config('factory.event_types'))],
            'guests' => ['required', 'integer', 'min:1', 'max:'.config('factory.max_guests')],
            'start_date' => ['required', 'date'],
            'days' => ['required', 'integer', 'min:1', 'max:14'],
            'buildup_days' => ['required', 'integer', 'min:0', 'max:7'],
            'breakdown_days' => ['required', 'integer', 'min:0', 'max:7'],
            'size' => ['required', Rule::in(array_keys(config('factory.sizes')))],
            'level' => ['required', Rule::in(array_keys(config('factory.levels')))],
            'extras' => ['array'], 'extras.*' => [Rule::in(config('factory.extras'))],
            'note' => ['nullable', 'string', 'max:3000'],
            'language' => ['required', Rule::in(array_keys(config('cluk.locales')))],
            'req_company' => ['nullable', 'string', 'max:160'],
            'req_vat' => ['nullable', 'string', 'max:30'],
            'req_name' => ['nullable', 'string', 'max:160'],
            'req_email' => ['nullable', 'email', 'max:190'],
            'req_phone' => ['nullable', 'string', 'max:40'],
            'load_in_time' => ['nullable', 'date_format:H:i'],
            'doors_time' => ['nullable', 'date_format:H:i'],
            'end_time' => ['nullable', 'date_format:H:i'],
            'load_out_time' => ['nullable', 'date_format:H:i'],
        ];
    }

    /** Times left empty get the house defaults. */
    private function valid(Request $request): array
    {
        $d = $request->validate($this->rules());
        foreach (['load_in_time' => '08:00', 'doors_time' => '18:00', 'end_time' => '01:00'] as $k => $default) {
            $d[$k] ??= $default;
        }

        return $d;
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->valid($request);
        $b = new Booking($d + ['extras' => array_values($d['extras'] ?? []), 'number' => Booking::nextNumber(), 'status' => 'request', 'source' => 'staff', 'owner_id' => $request->user()->id]);
        Bookings::matchKnown($b);
        $b->save();
        AuditLog::record('factory.requested', $b, ['status' => 'request', 'date' => $b->start_date->toDateString()]);

        return redirect()->route('factory.show', $b)->with('status', __('factory.flash.created', ['number' => $b->number]));
    }

    public function edit(Booking $booking): View
    {
        return view('factory.form', ['b' => $booking]);
    }

    public function update(Request $request, Booking $booking): RedirectResponse
    {
        abort_if(in_array($booking->status, ['declined', 'cancelled', 'done'], true), 409);
        $d = $this->valid($request);
        $booking->fill($d + ['extras' => array_values($d['extras'] ?? [])]);
        if ($booking->holds() && $booking->clashes()) {
            return back()->withInput()->withErrors(['start_date' => __('factory.errors.taken')]);
        }
        $booking->save();
        Tasks::sync($booking);

        return redirect()->route('factory.show', $booking)->with('status', __('factory.flash.saved'));
    }

    public function hold(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless(in_array($booking->status, ['request', 'option'], true), 409);
        Bookings::hold($booking, $request->user());

        return back()->with('status', __('factory.flash.held', ['date' => $booking->option_until->format('d/m H:i')]));
    }

    public function decline(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless(in_array($booking->status, ['request', 'option'], true), 409);
        Bookings::decline($booking, $request->validate(['reason' => ['required', 'string', 'max:500']])['reason'], $request->user());

        return redirect()->route('factory.index')->with('status', __('factory.flash.declined'));
    }

    public function quote(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless(in_array($booking->status, ['request', 'option'], true), 409);
        abort_unless($request->user()->canUseModule('sales', 2), 403);
        $quote = Bookings::makeQuote($booking, $request->user());

        return redirect()->route('quotes.show', $quote)->with('status', __('factory.flash.quote', ['number' => $quote->number]));
    }

    /** Staff confirm by hand (e.g. signed on paper). */
    public function confirm(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless(in_array($booking->status, ['request', 'option'], true) && $request->user()->canUseModule('factory', 3), 403);
        if ($booking->clashes()) {
            return back()->withErrors(['start_date' => __('factory.errors.taken')]);
        }
        Bookings::confirm($booking, $request->user());

        return back()->with('status', __('factory.flash.confirmed'));
    }

    public function checks(Request $request, Booking $booking): RedirectResponse
    {
        $booking->update(['checks' => collect(Booking::CHECKS)->mapWithKeys(fn ($c) => [$c => $request->boolean('check_'.$c)])->all()]);

        return back()->with('status', __('factory.flash.saved'));
    }

    public function status(Request $request, Booking $booking): RedirectResponse
    {
        $to = $request->validate(['to' => ['required', Rule::in(['done', 'cancelled'])]])['to'];
        abort_unless($booking->status === 'confirmed' || ($to === 'cancelled' && in_array($booking->status, ['request', 'option'], true)), 409);
        $booking->update(['status' => $to]);
        if ($to === 'cancelled') {
            Tasks::dropOpen($booking);
        }
        AuditLog::record('factory.'.$to, $booking, [], $request->user());

        return back()->with('status', __('factory.flash.'.$to));
    }

    /** Equipment for the event: a rental quote on the same deal. */
    public function gear(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless(in_array($booking->status, ['confirmed', 'done'], true), 409);
        abort_unless($request->user()->canUseModule('sales', 2), 403);
        $quote = Bookings::gearQuote($booking, $request->user());

        return redirect()->route('quotes.show', $quote)->with('status', __('factory.gear.made', ['number' => $quote->number]));
    }

    /** The gear is part of Advanced / All-in: no prices, straight to order allocation. */
    public function gearIncluded(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless($request->user()->canUseModule('sales', 2) && $booking->gear_quote_id, 403);
        $order = Bookings::bookGearIncluded($booking, $request->user());

        return redirect()->route('factory.show', $booking)->with('status', __('factory.gear.booked', ['number' => $order->number]));
    }

    /** The venue booking confirmation to sign, in the customer's language. */
    public function print(Request $request, Booking $booking): View
    {
        $type = $request->query('type') === 'pf' ? 'pf' : 'bc';
        abort_unless(in_array($booking->status, ['option', 'confirmed', 'done'], true), 404);
        $booking->load(['company', 'contact', 'quote']);
        $quote = $booking->currentQuote()?->load('lines');
        abort_if($type === 'pf' && $quote?->status !== 'accepted', 404);
        app()->setLocale(array_key_exists($booking->language, config('cluk.locales')) ? $booking->language : 'en');

        return view('factory.doc', ['b' => $booking, 'type' => $type, 'q' => $quote, 'me' => CompanyDetail::current()]);
    }
}
