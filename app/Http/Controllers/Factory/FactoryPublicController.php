<?php

namespace App\Http\Controllers\Factory;

use App\Http\Controllers\Controller;
use App\Models\Equipment\Item;
use App\Models\Factory\Booking;
use App\Services\Factory\Bookings;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The public Cluk Factory booking page: pick a date, a size and a level, then ask a quote or hold the date. */
class FactoryPublicController extends Controller
{
    private function locale(Request $request): string
    {
        $codes = array_keys(config('cluk.locales'));
        $lang = $request->query('lang', session('factory_lang', $request->getPreferredLanguage($codes) ?? 'en'));
        $lang = in_array($lang, $codes, true) ? $lang : 'en';
        session(['factory_lang' => $lang]);
        app()->setLocale($lang);

        return $lang;
    }

    public function show(Request $request): View
    {
        $this->locale($request);
        $from = today()->startOfMonth();
        $until = today()->addMonths((int) config('factory.months_ahead'))->endOfMonth();
        $taken = collect(Booking::occupancy($from, $until))->map(fn ($o) => $o['booking']->status === 'option' ? 'o' : 'b')->all();
        $prices = Item::whereIn('number', array_merge(array_column(config('factory.sizes'), 2), array_filter(config('factory.levels'))))->pluck('day_price_cents', 'number');

        return view('factory.book', [
            'taken' => $taken,
            'sizes' => collect(config('factory.sizes'))->map(fn ($s, $code) => ['code' => $code, 'floor' => $s[0], 'entrance' => $s[1], 'price' => (int) ($prices[$s[2]] ?? 0)])->values(),
            'advanced' => (int) ($prices[config('factory.levels.advanced')] ?? 0),
            'sent' => session('factory_sent') ? Booking::find(session('factory_sent')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $locale = $this->locale($request);
        if ($request->filled('website')) {
            return redirect()->route('factory.book'); // a robot filled the hidden field
        }
        $d = $request->validate([
            'event_type' => ['required', Rule::in(config('factory.event_types'))],
            'guests' => ['required', 'integer', 'min:1', 'max:'.config('factory.max_guests')],
            'start_date' => ['required', 'date', 'after:today', 'before:'.today()->addMonths((int) config('factory.months_ahead') + 1)->toDateString()],
            'days' => ['required', 'integer', 'min:1', 'max:'.config('factory.max_days')],
            'buildup_days' => ['required', 'integer', 'min:0', 'max:'.config('factory.max_buildup')],
            'breakdown_days' => ['required', 'integer', 'min:0', 'max:'.config('factory.max_breakdown')],
            'size' => ['required', Rule::in(array_keys(config('factory.sizes')))],
            'level' => ['required', Rule::in(array_keys(config('factory.levels')))],
            'extras' => ['array'], 'extras.*' => [Rule::in(config('factory.extras'))],
            'note' => ['nullable', 'string', 'max:3000'],
            'req_company' => ['required', 'string', 'max:160'],
            'req_vat' => ['nullable', 'string', 'max:30'],
            'req_name' => ['required', 'string', 'max:160'],
            'req_email' => ['required', 'email', 'max:190'],
            'req_phone' => ['nullable', 'string', 'max:40'],
        ]);
        if ((int) $d['buildup_days'] > 0 && today()->addDay()->gt(Carbon::parse($d['start_date'])->subDays((int) $d['buildup_days']))) {
            return back()->withInput()->withErrors(['start_date' => __('factory.errors.buildup_past')]);
        }
        $booking = Bookings::fromWeb($d + ['extras' => array_values($d['extras'] ?? [])], $request->input('action') === 'hold', $locale);

        return redirect()->route('factory.book')->with('factory_sent', $booking->id);
    }
}
