<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\Afrit6\Guest;
use App\Models\Member;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Member portal › Guests: who a member expects, welcomed on the entrance TV on that day. */
class GuestController extends Controller
{
    private function member(Request $request): Member
    {
        return $request->user('member');
    }

    public function index(Request $request): View
    {
        $m = $this->member($request);

        return view('afrit6.portal.guests', [
            'upcoming' => Guest::where('member_id', $m->id)->where('kind', 'guest')->live()->whereDate('date', '>=', today())->orderBy('date')->orderBy('time')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $m = $this->member($request);
        $d = $request->validate([
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(90)->toDateString()],
            'time' => ['nullable', 'date_format:H:i'],
            'names' => ['required', 'string', 'max:1000'],
            'company' => ['nullable', 'string', 'max:120'],
            'on_screen' => ['boolean'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        // One line per guest: several people from one visit in one go.
        $names = collect(preg_split('/\r\n|\r|\n|;/', $d['names']))->map(fn ($n) => trim($n))->filter()->unique()->take(20);
        if ($names->isEmpty()) {
            return back()->withErrors(['names' => __('guests.name_needed')])->withInput();
        }
        foreach ($names as $n) {
            Guest::create(['member_id' => $m->id, 'date' => $d['date'], 'time' => $d['time'] ?? null, 'name' => mb_substr($n, 0, 120),
                'company' => $d['company'] ?? null, 'on_screen' => $request->boolean('on_screen'), 'note' => $d['note'] ?? null]);
        }

        return redirect()->route('afrit6.portal.guests')->with('status', trans_choice('guests.added', $names->count(), ['n' => $names->count()]));
    }

    public function cancel(Request $request, Guest $guest): RedirectResponse
    {
        abort_unless($guest->member_id === $this->member($request)->id && $guest->cancelled_at === null, 404);
        $guest->update(['cancelled_at' => now()]);

        return redirect()->route('afrit6.portal.guests')->with('status', __('guests.cancelled'));
    }
}
