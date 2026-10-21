<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyDetail;
use App\Models\Finance\PreInvoice;
use App\Services\Finance\PreInvoices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Finance › Pre-invoicing: every proforma passes here; the accountant sends it to OkiOki or back with a comment. */
class PreInvoiceController extends Controller
{
    public const FILTERS = ['check', 'back', 'sent', 'late', 'paid', 'all'];

    public function index(Request $request): View
    {
        $filt = in_array($request->query('f'), self::FILTERS, true) ? $request->query('f') : 'check';
        $flagOnly = $request->boolean('flags');
        $all = PreInvoice::with(['company', 'member.company', 'madeBy', 'reminder'])->where('status', '!=', 'cancelled')->latest('id')->limit(500)->get();
        $list = $all->filter(fn (PreInvoice $p) => match ($filt) {
            'all' => true,
            'late' => $p->isOverdue(),
            default => $p->status === $filt,
        })->filter(fn ($p) => ! $flagOnly || $p->reds() + $p->ambers() > 0)->values();
        $sel = $request->query('pf') ? $all->firstWhere('id', (int) $request->query('pf')) : null;
        $sel ??= $list->first();
        $sum = fn ($s) => (int) $all->where('status', $s)->sum('gross_cents');

        return view('finance.index', [
            'list' => $list, 'sel' => $sel, 'filt' => $filt, 'flagOnly' => $flagOnly,
            'tiles' => [
                'check' => [$all->where('status', 'check')->count(), $sum('check'), $all->where('status', 'check')->filter(fn ($p) => $p->reds())->count()],
                'back' => [$all->where('status', 'back')->count()],
                'sent' => [$all->where('status', 'sent')->count(), $sum('sent')],
                'late' => [$all->filter->isOverdue()->count(), (int) $all->filter->isOverdue()->sum('gross_cents')],
            ],
            'counts' => collect(self::FILTERS)->mapWithKeys(fn ($f) => [$f => $f === 'all' ? $all->count() : ($f === 'late' ? $all->filter->isOverdue()->count() : $all->where('status', $f)->count())]),
            'canDecide' => $request->user()->canUseModule('preinvoice', 2),
            'activity' => $sel ? AuditLog::with('actor')->where('subject_type', $sel->getMorphClass())->where('subject_id', $sel->id)->oldest('id')->get() : collect(),
        ]);
    }

    /** Send a source document to pre-invoicing (button on the order, booking, sale). */
    public function make(Request $request, string $type, int $id): RedirectResponse
    {
        abort_unless(in_array($type, PreInvoices::TYPES, true), 404);
        $u = $request->user();
        abort_unless(match ($type) {
            'rental' => $u->canUseModule('dryhire', 2),
            'charges' => $u->canUseModule('sales', 2) || $u->hasRight('orders.approve'),
            'factory' => $u->canUseModule('factory', 2),
            'sales' => $u->canUseModule('sales', 2),
            'afrit6' => $u->canUseModule('afrit6', 2),
        } || $u->canUseModule('preinvoice', 2), 403);
        $src = PreInvoices::source(new PreInvoice(['source_type' => $type, 'source_id' => $id]));
        abort_unless($src, 404);
        $pf = PreInvoices::make($type, $src, $request->user());

        return back()->with('status', __('finance.made', ['number' => $pf->number]));
    }

    public function accept(Request $request, PreInvoice $pf): RedirectResponse
    {
        $d = $request->validate(['invoice_number' => ['nullable', 'string', 'max:40'], 'override' => ['boolean']]);
        PreInvoices::accept($pf, $request->user(), $d['invoice_number'] ?? null, $request->boolean('override'));

        return redirect()->route('finance.index', ['pf' => $pf->id])->with('status', __('finance.accepted', ['number' => $pf->number, 'customer' => $pf->customerName()]));
    }

    public function back(Request $request, PreInvoice $pf): RedirectResponse
    {
        $d = $request->validate(['reason' => ['required', Rule::in(['address', 'vat', 'po', 'amount', 'margin', 'other'])], 'note' => ['required', 'string', 'max:800']]);
        PreInvoices::back($pf, $request->user(), $d['reason'], $d['note']);

        return redirect()->route('finance.index', ['pf' => $pf->id])->with('status', __('finance.sent_back', ['name' => $pf->madeBy?->name ?? '—']));
    }

    public function resend(Request $request, PreInvoice $pf): RedirectResponse
    {
        abort_unless($pf->made_by_id === $request->user()->id || $request->user()->canUseModule('preinvoice', 2) || $request->user()->canUseModule($pf->module === 'dryhire' ? 'dryhire' : $pf->module, 2), 403);
        $pf = PreInvoices::resend($pf, $request->user(), $request->validate(['reply' => ['nullable', 'string', 'max:800']])['reply'] ?? null);

        return redirect()->route('finance.index', ['pf' => $pf->id, 'f' => 'check'])->with('status', __('finance.resent'));
    }

    public function paid(Request $request, PreInvoice $pf): RedirectResponse
    {
        PreInvoices::paid($pf, $request->user());

        return back()->with('status', __('finance.marked_paid'));
    }

    public function cancel(Request $request, PreInvoice $pf): RedirectResponse
    {
        abort_unless(in_array($pf->status, ['check', 'back'], true), 409);
        $pf->update(['status' => 'cancelled']);
        AuditLog::record('finance.pf_cancelled', $pf, [], $request->user());

        return redirect()->route('finance.index')->with('status', __('finance.cancelled'));
    }

    public function late(Request $request, PreInvoice $pf): RedirectResponse
    {
        abort_unless($pf->isOverdue() || $pf->status === 'paid', 409);
        $d = $request->validate(['decision' => ['required', Rule::in(['now', 'on_payment', 'skip'])], 'reason' => ['nullable', 'required_if:decision,skip', 'string', 'max:500']]);
        PreInvoices::decide($pf, $d['decision'], $d['reason'] ?? null, $request->user());

        return back()->with('status', __('finance.late_saved'));
    }

    /** The invoice data to type into OkiOki (until the OkiOki connection is live). */
    public function print(PreInvoice $pf): View
    {
        return view('finance.print', ['pf' => $pf->load(['company', 'member']), 'me' => CompanyDetail::current()]);
    }
}
