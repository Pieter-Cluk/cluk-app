<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\Afrit6\Guest;
use App\Models\AuditLog;
use App\Models\WallScreen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Media › Messages › Entrance screen: what the CLUK team puts on the Afrit 6 entrance TV agenda — guests CLUK
 * expects (welcomed by name, "visiting CLUK") and short notices for the day. Members add their own guests in
 * their portal; those show here too.
 */
class EntranceController extends Controller
{
    private function canEdit(Request $request): bool
    {
        return $request->user()->canUseModule('sales', 2) || $request->user()->canUseModule('afrit6', 2);
    }

    public function index(Request $request): View
    {
        return view('media.messages.entrance', [
            'entries' => Guest::with(['member.company', 'user'])->live()->whereDate('date', '>=', today())->orderBy('date')->orderByRaw('time is null, time')->get(),
            'canEdit' => $this->canEdit($request),
            'screen' => WallScreen::where('view', 'entrance')->first(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($this->canEdit($request), 403);
        $d = $request->validate([
            'kind' => ['required', Rule::in(['guest', 'notice'])],
            'date' => ['required', 'date', 'after_or_equal:today', 'before_or_equal:'.today()->addDays(180)->toDateString()],
            'time' => ['nullable', 'date_format:H:i'],
            'names' => ['required_if:kind,guest', 'nullable', 'string', 'max:1000'],
            'company' => ['nullable', 'string', 'max:120'],
            'on_screen' => ['boolean'],
            'text' => ['required_if:kind,notice', 'nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:300'],
        ]);
        $base = ['kind' => $d['kind'], 'user_id' => $request->user()->id, 'date' => $d['date'], 'time' => $d['time'] ?? null, 'note' => $d['note'] ?? null];
        if ($d['kind'] === 'notice') {
            Guest::create($base + ['name' => trim($d['text']), 'on_screen' => true]);
            $n = 1;
        } else {
            $names = collect(preg_split('/\r\n|\r|\n|;/', (string) $d['names']))->map(fn ($x) => trim($x))->filter()->unique()->take(20);
            foreach ($names as $name) {
                Guest::create($base + ['name' => mb_substr($name, 0, 120), 'company' => $d['company'] ?? null, 'on_screen' => $request->boolean('on_screen')]);
            }
            $n = $names->count();
        }
        AuditLog::record('entrance.added', null, ['kind' => $d['kind'], 'date' => $d['date'], 'count' => $n], $request->user());

        return redirect()->route('messages.entrance')->with('status', __('entrance.saved'));
    }

    public function cancel(Request $request, Guest $entry): RedirectResponse
    {
        abort_unless($this->canEdit($request) && $entry->cancelled_at === null, 403);
        $entry->update(['cancelled_at' => now()]);
        AuditLog::record('entrance.removed', $entry->member, ['kind' => $entry->kind, 'date' => $entry->date->toDateString(), 'from_member' => (bool) $entry->member_id], $request->user());

        return redirect()->route('messages.entrance')->with('status', __('entrance.removed'));
    }
}
