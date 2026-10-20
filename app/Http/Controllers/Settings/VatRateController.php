<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyDetail;
use App\Models\VatRate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings › VAT rates. A government change is planned with "schedule a
 * change": the current rate ends the day before and a new one starts.
 */
class VatRateController extends Controller
{
    public const DEFAULTS = ['vat_default_be', 'vat_default_eu_business', 'vat_default_eu_private', 'vat_default_outside_eu'];

    public function index(Request $request): View
    {
        $rates = VatRate::orderBy('code')->orderBy('valid_from')->get();
        $editing = match (true) {
            $request->query('edit') === 'new' => new VatRate(['zone' => 'be', 'valid_from' => today()]),
            $request->filled('edit') => $rates->firstWhere('id', $request->integer('edit')),
            default => null,
        };

        return view('settings.vat-rates', [
            'rates' => $rates,
            'editing' => $editing,
            'scheduling' => $editing?->exists && $request->boolean('schedule') && $editing->status() === 'active',
            'company' => CompanyDetail::current(),
            'choices' => VatRate::activeChoices(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $rate = new VatRate($this->validated($request));
        $this->refuseOverlap($rate);
        $rate->save();
        AuditLog::record('vat.created', $rate, ['name' => $rate->name, 'rate' => $rate->percent(), 'code' => $rate->code]);

        return redirect()->route('settings.vat.index')->with('status', __('settings.flash.vat_saved'));
    }

    public function update(Request $request, VatRate $rate): RedirectResponse
    {
        $rate->fill($this->validated($request));
        $this->refuseOverlap($rate);
        $changes = $rate->getDirty();
        unset($changes['updated_at']);
        $rate->save();
        if ($changes !== []) {
            AuditLog::record('vat.updated', $rate, ['changes' => array_map(fn ($v) => $v instanceof Carbon ? $v->toDateString() : $v, $changes)]);
        }

        return redirect()->route('settings.vat.index')->with('status', __('settings.flash.vat_saved'));
    }

    /** Plan a new percentage from a future date; the current rate ends the day before. */
    public function schedule(Request $request, VatRate $rate): RedirectResponse
    {
        $data = $request->validate([
            'new_rate' => ['required', 'numeric', 'between:0,100'],
            'from' => ['required', 'date', 'after:today'],
        ]);
        $from = Carbon::parse($data['from'])->startOfDay();

        if ($from->lte($rate->valid_from) || ($rate->valid_until && $from->gt($rate->valid_until->copy()->addDay()))) {
            throw ValidationException::withMessages(['from' => __('settings.errors.schedule_date')]);
        }

        $newBp = (int) round(((float) $data['new_rate']) * 100);
        $next = new VatRate([
            'name' => preg_replace('/^[\d.,]+%/', rtrim(rtrim(number_format($newBp / 100, 2, '.', ''), '0'), '.').'%', $rate->name),
            'rate_bp' => $newBp, 'code' => $rate->code, 'zone' => $rate->zone,
            'valid_from' => $from, 'valid_until' => $rate->valid_until,
        ]);
        $rate->valid_until = $from->copy()->subDay();
        $rate->save();
        $next->save();

        AuditLog::record('vat.scheduled', $next, ['code' => $next->code, 'from' => $rate->percent(), 'to' => $next->percent(), 'date' => $from->toDateString()]);

        return redirect()->route('settings.vat.index')->with('status', __('settings.flash.vat_scheduled', ['date' => $from->format('d/m/Y')]));
    }

    public function end(VatRate $rate): RedirectResponse
    {
        $rate->update(['valid_until' => today()]);
        AuditLog::record('vat.ended', $rate, ['name' => $rate->name]);

        return redirect()->route('settings.vat.index')->with('status', __('settings.flash.vat_saved'));
    }

    /**
     * Only a planned rate can be deleted (rates that were in force stay for
     * the history; end them instead). The rate before it runs on again.
     */
    public function destroy(VatRate $rate): RedirectResponse
    {
        if ($rate->status() !== 'planned') {
            throw ValidationException::withMessages(['rate' => __('settings.errors.vat_keep_history')]);
        }

        $previous = VatRate::where('code', $rate->code)
            ->whereDate('valid_until', $rate->valid_from->copy()->subDay()->toDateString())
            ->first();
        $previous?->update(['valid_until' => $rate->valid_until]);

        AuditLog::record('vat.deleted', $previous, ['name' => $rate->name, 'code' => $rate->code, 'from' => $rate->valid_from->toDateString()]);
        $rate->delete();

        return redirect()->route('settings.vat.index')->with('status', __('settings.flash.vat_deleted'));
    }

    public function updateDefaults(Request $request): RedirectResponse
    {
        $codes = VatRate::activeChoices()->pluck('code')->all();
        $data = $request->validate(array_fill_keys(self::DEFAULTS, ['required', Rule::in($codes)]));

        $company = CompanyDetail::current();
        $company->fill($data);
        $changes = $company->getDirty();
        unset($changes['updated_at']);
        $company->save();
        if ($changes !== []) {
            AuditLog::record('vat.defaults_changed', $company, ['changes' => $changes]);
        }

        return redirect()->route('settings.vat.index')->with('status', __('settings.flash.vat_saved'));
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'rate' => ['required', 'numeric', 'between:0,100'],
            'code' => ['required', 'string', 'max:4', 'regex:/^[0-9A-Z]+$/'],
            'zone' => ['required', Rule::in(VatRate::ZONES)],
            'valid_from' => ['required', 'date'],
            'valid_until' => ['nullable', 'date', 'after_or_equal:valid_from'],
        ]);

        return [
            'name' => $data['name'],
            'rate_bp' => (int) round(((float) $data['rate']) * 100),
            'code' => $data['code'],
            'zone' => $data['zone'],
            'valid_from' => Carbon::parse($data['valid_from'])->startOfDay(),
            'valid_until' => isset($data['valid_until']) ? Carbon::parse($data['valid_until'])->startOfDay() : null,
        ];
    }

    private function refuseOverlap(VatRate $rate): void
    {
        if ($rate->overlapsAnother()) {
            throw ValidationException::withMessages(['valid_from' => __('settings.errors.vat_overlap', ['code' => $rate->code])]);
        }
    }
}
