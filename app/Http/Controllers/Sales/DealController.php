<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Sales\Deal;
use App\Models\Sales\DealActivity;
use App\Models\Sales\IncomingMessage;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * The sales pipeline: deals from new to won or lost.
 * Level 1 sees, level 2 works on own and unassigned deals, level 3 on all.
 */
class DealController extends Controller
{
    public function index(Request $request): View
    {
        $me = $request->user();
        $module = in_array($request->query('module'), Deal::MODULES, true) ? $request->query('module') : null;
        $owner = $request->query('owner');
        $view = in_array($request->query('view'), ['board', 'closed'], true) ? $request->query('view') : 'board';

        $base = Deal::with(['company', 'contact', 'owner'])
            ->when($module, fn ($q) => $q->where('module', $module))
            ->when($owner === 'me', fn ($q) => $q->where('owner_id', $me->id))
            ->when(is_numeric($owner), fn ($q) => $q->where('owner_id', (int) $owner));

        $open = (clone $base)->open()->orderByRaw('event_start is null, event_start')->get();
        $columns = [];
        foreach (Deal::OPEN as $stage) {
            $deals = $open->where('stage', $stage)->values();
            $columns[$stage] = ['deals' => $deals, 'sum' => $deals->sum('value_cents'), 'weighted' => $deals->sum(fn ($d) => $d->weightedCents())];
        }

        return view('sales.deals.index', [
            'columns' => $columns,
            'closed' => $view === 'closed' ? (clone $base)->whereIn('stage', Deal::CLOSED)->latest('closed_at')->paginate(50)->withQueryString() : null,
            'view' => $view, 'module' => $module, 'owner' => $owner,
            'owners' => $this->owners(),
            'incomingCount' => IncomingMessage::where('status', 'new')->count(),
            'canWork' => $me->canUseModule('sales', 2),
            'totals' => ['value' => $open->sum('value_cents'), 'weighted' => $open->sum(fn ($d) => $d->weightedCents()), 'count' => $open->count()],
        ]);
    }

    public function show(Request $request, Deal $deal): View
    {
        $deal->load(['company', 'contact', 'owner', 'closedBy', 'incoming', 'activities.user', 'quotes']);

        return view('sales.deals.show', [
            'deal' => $deal,
            'canWork' => $this->canWork($request->user(), $deal),
            'owners' => $this->owners(),
            'contacts' => $deal->company ? $deal->company->contacts()->orderBy('first_name')->get() : collect(),
            'openDeals' => $deal->company_id ? Deal::open()->where('company_id', $deal->company_id)->whereKeyNot($deal->id)->get() : collect(),
        ]);
    }

    /** People who can own deals: sales module level 2 or more. */
    private function owners()
    {
        return User::with(['role.moduleLevels', 'moduleOverrides'])->where('status', User::STATUS_ACTIVE)->orderBy('first_name')->get()
            ->filter(fn (User $u) => $u->canUseModule('sales', 2))->values();
    }

    private function canWork(User $me, Deal $deal): bool
    {
        return $deal->canBeWorkedBy($me);
    }

    private function guard(Request $request, Deal $deal): void
    {
        abort_unless($this->canWork($request->user(), $deal), 403);
    }

    public function create(Request $request): View
    {
        return view('sales.deals.create', [
            'owners' => $this->owners(),
            'companies' => Company::active()->orderBy('name')->get(['id', 'name']),
            'company' => $request->query('company') ? Company::find($request->query('company')) : null,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->validateDeal($request, true);
        $deal = Deal::create($d + ['number' => Deal::nextNumber(), 'stage' => 'new', 'owner_id' => $d['owner_id'] ?? $request->user()->id]);
        $deal->log('system', __('deals.log.created', ['source' => $deal->source ? __('deals.sources.'.$deal->source) : '—']));

        return redirect()->route('deals.show', $deal)->with('status', __('deals.flash.created', ['number' => $deal->number]));
    }

    public function update(Request $request, Deal $deal): RedirectResponse
    {
        $this->guard($request, $deal);
        $d = $this->validateDeal($request, false);
        if (isset($d['owner_id']) && $d['owner_id'] !== $deal->owner_id && ! $request->user()->canUseModule('sales', 3) && $d['owner_id'] !== $request->user()->id) {
            // Level 2 can take a deal or keep it, but not give it to someone else.
            unset($d['owner_id']);
        }
        $deal->fill($d);
        $changes = array_keys($deal->getDirty());
        $deal->save();
        if ($changes) {
            $deal->log('system', __('deals.log.updated', ['fields' => implode(', ', array_map(fn ($f) => __('deals.fields.'.$f), $changes))]));
        }

        return back()->with('status', __('deals.flash.saved'));
    }

    private function validateDeal(Request $request, bool $new): array
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'module' => ['required', Rule::in(Deal::MODULES)],
            'source' => ['nullable', Rule::in(Deal::SOURCES)],
            'company_id' => ['nullable', 'integer', 'exists:companies,id'],
            'contact_id' => ['nullable', 'integer', 'exists:contacts,id'],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'event_start' => ['nullable', 'date'],
            'event_end' => ['nullable', 'date', 'after_or_equal:event_start'],
            'location' => ['nullable', 'string', 'max:160'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);
        $out = collect($d)->except('value')->all();
        $out['value_cents'] = (int) round(((float) ($d['value'] ?? 0)) * 100);

        return $out;
    }

    /** Qualification checklist: date & location, budget, who decides. */
    public function checks(Request $request, Deal $deal): RedirectResponse
    {
        $this->guard($request, $deal);
        abort_unless($deal->isOpen(), 404);
        $deal->update([
            'check_date' => $request->boolean('check_date'),
            'check_budget' => $request->boolean('check_budget'),
            'check_decider' => $request->boolean('check_decider'),
        ]);

        return back()->with('status', __('deals.flash.saved'));
    }

    /** Move along the pipeline. Qualifying needs the three checks. */
    public function move(Request $request, Deal $deal): RedirectResponse
    {
        $this->guard($request, $deal);
        $to = $request->validate(['stage' => ['required', Rule::in(Deal::OPEN)]])['stage'];
        abort_unless($deal->isOpen(), 404);
        if ($to !== 'new' && ! $deal->qualified()) {
            throw ValidationException::withMessages(['stage' => __('deals.errors.qualify_first')]);
        }
        $from = $deal->stage;
        $deal->update(['stage' => $to]);
        $deal->log('stage', __('deals.log.moved', ['from' => __('deals.stages.'.$from), 'to' => __('deals.stages.'.$to)]));

        return back()->with('status', __('deals.flash.moved', ['stage' => __('deals.stages.'.$to)]));
    }

    public function win(Request $request, Deal $deal): RedirectResponse
    {
        $this->guard($request, $deal);
        abort_unless($deal->isOpen(), 404);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $this->close($deal, 'won', $request->user(), ['close_note' => $note]);
        if ($deal->company && ! $deal->company->is_customer) {
            $deal->company->forceFill(['is_customer' => true])->save();
        }

        return back()->with('status', __('deals.flash.won'));
    }

    public function lose(Request $request, Deal $deal): RedirectResponse
    {
        $this->guard($request, $deal);
        abort_unless($deal->isOpen(), 404);
        $d = $request->validate([
            'reason' => ['required', Rule::in(Deal::LOST_REASONS)],
            'competitor' => [Rule::requiredIf($request->input('reason') === 'competitor'), 'nullable', 'string', 'max:120'],
            'note' => ['nullable', 'string', 'max:500'],
        ]);
        $this->close($deal, 'lost', $request->user(), ['lost_reason' => $d['reason'], 'competitor' => $d['competitor'] ?? null, 'close_note' => $d['note'] ?? null]);

        return back()->with('status', __('deals.flash.lost'));
    }

    /** Not a real opportunity (spam, wrong person): only from New or Qualified, not counted in the win rate. */
    public function disqualify(Request $request, Deal $deal): RedirectResponse
    {
        $this->guard($request, $deal);
        abort_unless(in_array($deal->stage, ['new', 'qualified'], true), 404);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $this->close($deal, 'disqualified', $request->user(), ['close_note' => $note]);

        return back()->with('status', __('deals.flash.disqualified'));
    }

    private function close(Deal $deal, string $stage, User $by, array $extra): void
    {
        $from = $deal->stage;
        $deal->update(['stage' => $stage, 'closed_at' => now(), 'closed_by_id' => $by->id] + $extra);
        $text = __('deals.log.closed_'.$stage, ['reason' => $deal->lost_reason ? __('deals.lost_reasons.'.$deal->lost_reason) : '', 'competitor' => $deal->competitor ?? '']);
        $deal->log('stage', trim($text.($deal->close_note ? ' · '.$deal->close_note : '')).' ('.__('deals.stages.'.$from).')', $by);
    }

    public function reopen(Request $request, Deal $deal): RedirectResponse
    {
        $this->guard($request, $deal);
        abort_if($deal->isOpen(), 404);
        $to = $deal->qualified() ? 'negotiation' : 'new';
        $deal->update(['stage' => $to, 'closed_at' => null, 'closed_by_id' => null, 'lost_reason' => null, 'competitor' => null, 'close_note' => null]);
        $deal->log('stage', __('deals.log.reopened', ['to' => __('deals.stages.'.$to)]));

        return back()->with('status', __('deals.flash.reopened'));
    }

    public function note(Request $request, Deal $deal): RedirectResponse
    {
        abort_unless($request->user()->canUseModule('sales', 2), 403);
        $d = $request->validate(['kind' => ['required', Rule::in(DealActivity::KINDS)], 'body' => ['required', 'string', 'max:5000']]);
        $deal->log($d['kind'], $d['body']);

        return back()->with('status', __('deals.flash.noted'));
    }

    /** Quick add of a contact person to the deal's company. */
    public function addContact(Request $request, Deal $deal): RedirectResponse
    {
        $this->guard($request, $deal);
        $d = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['nullable', 'string', 'max:80'],
            'email' => ['nullable', 'email', 'max:190'], 'phone' => ['nullable', 'string', 'max:40'],
        ]);
        if ($d['email'] ?? null) {
            $d['email'] = mb_strtolower($d['email']);
        }
        $contact = ($d['email'] ?? null) ? Contact::firstOrCreate(['email' => $d['email']], $d) : Contact::create($d);
        if ($deal->company && ! $deal->company->contacts()->whereKey($contact->id)->exists()) {
            $deal->company->contacts()->attach($contact->id);
        }
        $deal->update(['contact_id' => $contact->id]);

        return back()->with('status', __('deals.flash.saved'));
    }
}
