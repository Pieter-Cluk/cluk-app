<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Sales\LabourType;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Settings › Labour rates: the kinds of labour and their rate (per hour or day, excl. VAT) for rental and Cluk Factory quotes. */
class LabourController extends Controller
{
    public function index(): View
    {
        return view('settings.labour', ['types' => LabourType::orderBy('position')->orderBy('id')->get(), 'locales' => array_keys(config('cluk.locales'))]);
    }

    /** Save all rows at once; a filled-in "new" row adds a type. Rate changes are logged. */
    public function save(Request $request): RedirectResponse
    {
        $locales = array_keys(config('cluk.locales'));
        $d = $request->validate([
            't' => ['array'], 't.*.names' => ['array'], 't.*.names.*' => ['nullable', 'string', 'max:80'],
            't.*.unit' => ['required', Rule::in(LabourType::UNITS)], 't.*.price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            't.*.active' => ['boolean'],
            'new' => ['array'], 'new.names' => ['array'], 'new.names.*' => ['nullable', 'string', 'max:80'],
            'new.unit' => ['nullable', Rule::in(LabourType::UNITS)], 'new.price' => ['nullable', 'numeric', 'min:0', 'max:100000'],
        ]);
        $clean = fn (array $n) => array_filter(array_map(fn ($v) => trim((string) $v), array_intersect_key($n, array_flip($locales))), fn ($v) => $v !== '');
        $cents = fn ($p) => $p === null || $p === '' ? null : (int) round((float) $p * 100);
        $changes = [];
        foreach (LabourType::all() as $type) {
            $row = $d['t'][$type->id] ?? null;
            if (! $row) {
                continue;
            }
            $names = $clean($row['names'] ?? []) ?: $type->names;
            $new = ['names' => $names, 'unit' => $row['unit'], 'price_cents' => $cents($row['price'] ?? null), 'active' => (bool) ($row['active'] ?? false)];
            if ($type->price_cents !== $new['price_cents'] || $type->unit !== $new['unit']) {
                $changes[] = $type->name('en').': '.($type->price_cents === null ? '—' : number_format($type->price_cents / 100, 2)).'/'.$type->unit.' → '.($new['price_cents'] === null ? '—' : number_format($new['price_cents'] / 100, 2)).'/'.$new['unit'];
            }
            $type->update($new);
        }
        $newNames = $clean($d['new']['names'] ?? []);
        if ($newNames) {
            $t = LabourType::create(['names' => $newNames, 'unit' => $d['new']['unit'] ?? 'hour', 'price_cents' => $cents($d['new']['price'] ?? null), 'active' => true,
                'position' => (int) LabourType::max('position') + 1]);
            $changes[] = 'new: '.$t->name('en');
        }
        if ($changes) {
            AuditLog::record('labour.rates_changed', null, ['changes' => implode(' · ', $changes)], $request->user());
        }

        return redirect()->route('settings.labour.index')->with('status', __('settings.labour.saved'));
    }
}
