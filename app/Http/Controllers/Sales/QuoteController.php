<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CompanyDetail;
use App\Models\Equipment\Item;
use App\Models\ProductSales\SalesQuote;
use App\Models\Sales\Deal;
use App\Models\Sales\Quote;
use App\Models\Sales\QuoteLine;
use App\Services\Afrit6\Pricing;
use App\Services\Products\ItemSearch;
use App\Services\Rental\Availability;
use App\Services\Rental\QuoteAnswer;
use App\Services\Rental\QuoteMails;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Rental quotes: the editor, sending, versions. */
class QuoteController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['draft', 'sent', 'accepted', 'declined', 'change'], true) ? $request->query('status') : null;
        $quotes = Quote::with(['company', 'contact', 'owner'])
            ->where('status', '!=', 'replaced')
            ->when($status, fn ($q) => $q->where('status', $status))
            ->when($request->query('owner') === 'me', fn ($q) => $q->where('owner_id', $request->user()->id))
            ->latest('updated_at')->paginate(40)->withQueryString();

        return view('sales.quotes.index', ['quotes' => $quotes, 'status' => $status, 'owner' => $request->query('owner')]);
    }

    public function show(Request $request, Quote $quote): View
    {
        $quote->load(['lines.item.contents', 'company', 'contact', 'deal', 'owner', 'sentBy']);
        $avail = new Availability($quote->out_from, $quote->out_until, $quote->number);
        $canWork = $quote->deal ? $quote->deal->canBeWorkedBy($request->user()) : $request->user()->canUseModule('sales', 2);

        return view('sales.quotes.show', [
            'quote' => $quote,
            'avail' => $avail,
            'canEdit' => $canWork && $quote->isDraft(),
            'canWork' => $canWork,
            'versions' => Quote::where('number', $quote->number)->orderByDesc('version')->get(['id', 'version', 'status', 'sent_at', 'gross_cents']),
            'contacts' => $quote->company ? $quote->company->contacts()->orderBy('first_name')->get() : collect(),
            'logistics' => $quote->loadFigures(),
        ]);
    }

    /** Printable version (browser → PDF). Also used for the customer. */
    public function print(Quote $quote): View
    {
        $quote->load(['lines', 'company', 'contact', 'owner']);
        app()->setLocale($quote->language); // the document is in the customer's language

        return view('sales.quotes.print', ['quote' => $quote, 'me' => CompanyDetail::current()]);
    }

    private function guard(Request $request, Quote $quote, bool $draft = true): void
    {
        $ok = $quote->deal ? $quote->deal->canBeWorkedBy($request->user()) : $request->user()->canUseModule('sales', 2);
        abort_unless($ok, 403);
        if ($draft) {
            abort_unless($quote->isDraft(), 409);
        }
    }

    /** New quote from a deal: customer and dates come from the deal. */
    public function store(Request $request, Deal $deal): RedirectResponse
    {
        abort_unless($deal->canBeWorkedBy($request->user()), 403);
        if ($deal->module === 'sales') {
            return $this->salesQuote($request, $deal);
        }
        $start = $deal->event_start ?? today()->addWeek();
        $end = $deal->event_end ?? $start;
        $quote = Quote::create([
            'number' => Quote::nextNumber(), 'version' => 1, 'status' => 'draft', 'deal_id' => $deal->id,
            'company_id' => $deal->company_id, 'contact_id' => $deal->contact_id, 'owner_id' => $deal->owner_id ?? $request->user()->id,
            'title' => $deal->title, 'language' => $deal->contact?->language ?: ($deal->company?->language ?: 'nl'),
            'out_from' => $start->copy()->subDay()->setTime(14, 0), 'out_until' => $end->copy()->addDay()->setTime(10, 0),
            'use_from' => $start, 'use_until' => $end, 'location' => $deal->location,
        ]);
        $quote->recalculate();
        $deal->log('system', __('quotes.log.created', ['number' => $quote->number]));

        return redirect()->route('quotes.show', $quote)->with('status', __('quotes.flash.created', ['number' => $quote->number]));
    }

    /** A deal of the kind "sales" gets a sales quote (S-QUO): products from the sales catalogue, supplier prices, margin check. */
    private function salesQuote(Request $request, Deal $deal): RedirectResponse
    {
        if (! $deal->company_id) {
            return back()->withErrors(['company_id' => __('quotes.errors.sales_needs_company')]);
        }
        $company = $deal->company;
        $q = SalesQuote::create([
            'number' => SalesQuote::nextNumber(), 'status' => 'draft', 'deal_id' => $deal->id, 'company_id' => $company->id,
            'contact_id' => $deal->contact_id ?? $company->contacts()->orderByDesc('company_contact.is_main')->value('contacts.id'),
            'owner_id' => $deal->owner_id ?? $request->user()->id, 'title' => $deal->title,
            'language' => $deal->contact?->language ?: ($company->language ?: 'nl'),
            'valid_until' => today()->addDays(CompanyDetail::current()->quote_valid_days ?: 30),
        ]);
        $q->recalculate();
        AuditLog::record('psales.created', $q, ['number' => $q->number, 'deal' => $deal->number], $request->user());
        $deal->log('system', __('quotes.log.created', ['number' => $q->number]));

        return redirect()->route('psales.show', $q)->with('status', __('quotes.flash.created', ['number' => $q->number]));
    }

    /** Save the header and all lines in one go. */
    public function update(Request $request, Quote $quote): RedirectResponse
    {
        $this->guard($request, $quote);
        $d = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'language' => ['required', Rule::in(array_keys(config('cluk.locales')))],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'out_from' => ['required', 'date'],
            'out_until' => ['required', 'date', 'after:out_from'],
            'use_from' => ['required', 'date'],
            'use_until' => ['required', 'date', 'after_or_equal:use_from'],
            'location' => ['nullable', 'string', 'max:200'],
            'intro' => ['nullable', 'string', 'max:2000'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'project_discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'logistics' => ['nullable', Rule::in(Quote::logisticsOptions())],
            'transport' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'transport_note' => ['nullable', 'string', 'max:300'],
            'lines' => ['array'],
            'lines.*.qty' => ['required', 'numeric', 'min:0', 'max:100000'],
            'lines.*.name' => ['required', 'string', 'max:200'],
            'lines.*.price' => ['nullable', 'numeric', 'min:-1000000', 'max:1000000'],
            'lines.*.factor' => ['nullable', 'numeric', 'min:0', 'max:999'],
            'lines.*.discount' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'lines.*.position' => ['nullable', 'integer', 'min:0', 'max:9999'],
        ]);
        $useFrom = Carbon::parse($d['use_from']);
        $useUntil = Carbon::parse($d['use_until']);
        $outFrom = Carbon::parse($d['out_from']);
        $outUntil = Carbon::parse($d['out_until']);
        if ($useFrom->lt($outFrom->copy()->startOfDay()) || $useUntil->gt($outUntil->copy()->endOfDay())) {
            throw ValidationException::withMessages(['use_from' => __('quotes.errors.use_inside_out')]);
        }

        DB::transaction(function () use ($quote, $d) {
            $quote->fill(collect($d)->only(['title', 'language', 'contact_id', 'out_from', 'out_until', 'use_from', 'use_until', 'location', 'intro', 'notes'])->all());
            $quote->project_discount_bp = (int) round(((float) ($d['project_discount'] ?? 0)) * 100);
            if (array_key_exists('logistics', $d)) {
                $quote->logistics = $d['logistics'] ?: 'open';
                $quote->transport_cents = (int) round(((float) ($d['transport'] ?? 0)) * 100);
                $quote->transport_note = $d['transport_note'] ?? null;
            }
            $quote->save();
            foreach ($d['lines'] ?? [] as $id => $l) {
                $line = $quote->lines()->whereKey($id)->first();
                if (! $line) {
                    continue;
                }
                $line->fill([
                    'qty' => $l['qty'], 'name' => $l['name'],
                    'factor' => ($l['factor'] ?? '') === '' ? null : $l['factor'],
                    'discount_bp' => (int) round(((float) ($l['discount'] ?? 0)) * 100),
                    'position' => $l['position'] ?? $line->position,
                ]);
                if (array_key_exists('price', $l) && $l['price'] !== null && $l['price'] !== '') {
                    $line->unit_price_cents = (int) round(((float) $l['price']) * 100);
                }
                $line->save();
            }
            $quote->load('company');
            $quote->recalculate();
        });

        return redirect()->route('quotes.show', $quote)->with('status', __('quotes.flash.saved'));
    }

    /** Add a catalogue item (by number), a charge or a text line. */
    public function addLine(Request $request, Quote $quote): RedirectResponse
    {
        $this->guard($request, $quote);
        $d = $request->validate([
            'kind' => ['required', Rule::in(QuoteLine::KINDS)],
            'number' => ['required_if:kind,item', 'nullable', 'string', 'max:40'],
            'name' => ['required_unless:kind,item', 'nullable', 'string', 'max:200'],
            'qty' => ['nullable', 'numeric', 'min:0', 'max:100000'],
            'price' => ['nullable', 'numeric', 'min:-1000000', 'max:1000000'],
        ]);
        $position = (int) $quote->lines()->max('position') + 1;
        $qty = (float) ($d['qty'] ?? 1) ?: 1;

        if ($d['kind'] === 'item') {
            $item = Item::active()->where('number', trim($d['number']))->first()
                ?? Item::active()->where('name', trim($d['number']))->first();
            if (! $item) {
                throw ValidationException::withMessages(['number' => __('quotes.errors.unknown_item', ['number' => $d['number']])]);
            }
            $existing = $quote->lines()->where('item_id', $item->id)->first();
            if ($existing) {
                $existing->update(['qty' => (float) $existing->qty + $qty]);
            } else {
                $quote->lines()->create([
                    'kind' => 'item', 'item_id' => $item->id, 'number' => $item->number, 'name' => $item->name,
                    'qty' => $qty, 'unit_price_cents' => (int) $item->day_price_cents, 'position' => $position,
                ]);
            }
        } else {
            $quote->lines()->create([
                'kind' => $d['kind'], 'name' => $d['name'], 'qty' => $d['kind'] === 'text' ? 0 : $qty,
                'unit_price_cents' => $d['kind'] === 'text' ? 0 : (int) round(((float) ($d['price'] ?? 0)) * 100),
                'factor' => $d['kind'] === 'charge' ? 1 : null, 'position' => $position,
            ]);
        }
        $quote->recalculate();

        return redirect()->to(route('quotes.show', $quote).'#add-item')->with('status', __('quotes.flash.line_added'));
    }

    public function removeLine(Request $request, Quote $quote, QuoteLine $line): RedirectResponse
    {
        $this->guard($request, $quote);
        abort_unless($line->quote_id === $quote->id, 404);
        $line->delete();
        $quote->recalculate();

        return redirect()->route('quotes.show', $quote)->with('status', __('quotes.flash.line_removed'));
    }

    /** Catalogue search for the editor (JSON): items with a price, free qty in the quote's period. */
    public function search(Request $request, Quote $quote): JsonResponse
    {
        $q = trim((string) $request->query('q'));
        $avail = new Availability($quote->out_from, $quote->out_until, $quote->number);
        $items = Item::active()->where('in_search', true)->where('kind', '!=', 'case')
            ->when($q !== '', fn ($x) => ItemSearch::words($x, $q))
            ->with(['contents', 'assets', 'productSubcategory', 'options.attribute'])->orderBy('name')->limit(12)->get();

        return response()->json($items->map(fn (Item $i) => [
            'number' => $i->number, 'name' => $i->name, 'kind' => $i->kind, 'category' => $i->productSubcategory?->label() ?? $i->category,
            'chips' => $i->optionLabels(),
            'price' => Pricing::eur((int) $i->day_price_cents), 'unit' => __('equipment.units.'.$i->price_unit),
            'free' => $avail->free($i),
        ])->values());
    }

    /** Send this version to the customer: prices and VAT are frozen from here on. */
    public function send(Request $request, Quote $quote): RedirectResponse
    {
        $this->guard($request, $quote);
        $d = $request->validate(['to' => ['required', 'email', 'max:190'], 'message' => ['nullable', 'string', 'max:3000']]);
        abort_if($quote->lines()->count() === 0, 422, __('quotes.errors.no_lines'));
        $me = CompanyDetail::current();
        $quote->load('company');
        $quote->recalculate();
        $quote->forceFill([
            'status' => 'sent', 'token' => $quote->token ?: Str::random(48), 'sent_at' => now(), 'sent_by_id' => $request->user()->id,
            'sent_to' => mb_strtolower($d['to']), 'valid_until' => today()->addDays($me->quote_valid_days ?: 30),
            'reserved_until' => today()->addDays($me->option_days ?: 3)->setTime(17, 0),
        ])->save();

        AuditLog::record('quote.sent', $quote, ['to' => $quote->sent_to, 'total' => Pricing::eur($quote->net_cents), 'version' => $quote->version]);
        if ($deal = $quote->deal) {
            $deal->forceFill(['value_cents' => $quote->net_cents])->save();
            if (in_array($deal->stage, ['new', 'qualified'], true)) {
                $from = $deal->stage;
                $deal->update(['stage' => 'proposal']);
                $deal->log('stage', __('deals.log.moved', ['from' => __('deals.stages.'.$from), 'to' => __('deals.stages.proposal')]));
            }
            $deal->log('email', __('quotes.log.sent', ['code' => $quote->code(), 'to' => $quote->sent_to, 'total' => Pricing::eur($quote->net_cents)]));
        }
        $ok = QuoteMails::sent($quote->load(['lines', 'contact', 'owner']), $d['message'] ?? null, $request->user());

        return redirect()->route('quotes.show', $quote)->with('status', $ok ? __('quotes.flash.sent', ['to' => $quote->sent_to]) : __('quotes.flash.sent_no_mail'));
    }

    /** New version: a copy as draft; the current one is replaced. */
    public function revise(Request $request, Quote $quote): RedirectResponse
    {
        $this->guard($request, $quote, false);
        abort_unless($quote->isLatest() && in_array($quote->status, ['sent', 'change', 'declined'], true), 409);
        $new = DB::transaction(function () use ($quote) {
            $copy = $quote->replicate(['token', 'sent_at', 'sent_by_id', 'sent_to', 'valid_until', 'reserved_until', 'answered_at', 'answered_name', 'po_number', 'answered_ip', 'decline_reason', 'answer_note']);
            $copy->version = $quote->version + 1;
            $copy->status = 'draft';
            $copy->save();
            foreach ($quote->lines as $line) {
                $copy->lines()->create($line->only(['position', 'kind', 'item_id', 'number', 'name', 'qty', 'unit_price_cents', 'factor', 'discount_bp']));
            }
            $quote->update(['status' => 'replaced', 'reserved_until' => null]);
            $copy->recalculate();

            return $copy;
        });
        $new->deal?->log('system', __('quotes.log.revised', ['code' => $new->code()]));

        return redirect()->route('quotes.show', $new)->with('status', __('quotes.flash.revised', ['n' => $new->version]));
    }

    /** The customer said yes by phone or mail: staff confirms on their behalf. */
    public function markAccepted(Request $request, Quote $quote): RedirectResponse
    {
        $this->guard($request, $quote, false);
        abort_unless(in_array($quote->status, ['sent', 'change'], true), 409);
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'po_number' => ['nullable', 'string', 'max:80']]);
        QuoteAnswer::accept($quote, $d['name'], $d['po_number'] ?? null, null, $request->user());

        return redirect()->route('quotes.show', $quote)->with('status', __('quotes.flash.accepted'));
    }
}
