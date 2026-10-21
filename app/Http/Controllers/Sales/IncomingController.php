<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Models\Sales\Deal;
use App\Models\Sales\IncomingMessage;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Incoming: everything that asks for something before it is a deal.
 * Each item becomes a new deal, joins an existing deal, gets a question
 * back, or is put aside as not relevant.
 */
class IncomingController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('status'), ['new', 'handled'], true) ? $request->query('status') : 'new';
        $channel = in_array($request->query('channel'), IncomingMessage::CHANNELS, true) ? $request->query('channel') : null;

        $items = IncomingMessage::with(['company', 'contact', 'deal', 'handledBy'])
            ->when($status === 'new', fn ($q) => $q->whereIn('status', ['new', 'asked']), fn ($q) => $q->whereIn('status', ['converted', 'linked', 'dismissed']))
            ->when($channel, fn ($q) => $q->where('channel', $channel))
            ->latest('received_at')->paginate(30)->withQueryString();

        return view('sales.incoming.index', [
            'items' => $items, 'status' => $status, 'channel' => $channel,
            'selected' => $request->query('open') ? IncomingMessage::with(['company', 'contact'])->find($request->query('open')) : null,
            'counts' => ['new' => IncomingMessage::whereIn('status', ['new', 'asked'])->count(), 'handled' => IncomingMessage::whereIn('status', ['converted', 'linked', 'dismissed'])->count()],
            'companies' => Company::active()->orderBy('name')->get(['id', 'name']),
            'openDeals' => Deal::open()->with('company')->orderBy('title')->get(['id', 'number', 'title', 'company_id']),
            'owners' => User::query()->where('status', User::STATUS_ACTIVE)->orderBy('first_name')->get()->filter(fn ($u) => $u->canUseModule('sales', 2))->values(),
            'canWork' => $request->user()->canUseModule('sales', 2),
        ]);
    }

    /** Log a request by hand: a call, a visit, a WhatsApp or a social message. */
    public function store(Request $request): RedirectResponse
    {
        $d = $request->validate([
            'channel' => ['required', Rule::in(IncomingMessage::CHANNELS)],
            'subject' => ['required', 'string', 'max:200'],
            'body' => ['nullable', 'string', 'max:10000'],
            'from_name' => ['nullable', 'string', 'max:160'],
            'from_email' => ['nullable', 'email', 'max:190'],
            'from_phone' => ['nullable', 'string', 'max:40'],
            'company_name' => ['nullable', 'string', 'max:160'],
            'module' => ['nullable', Rule::in(Deal::MODULES)],
        ]);
        $item = new IncomingMessage($d + ['received_at' => now()]);
        $item->matchKnown();
        $item->save();

        return redirect()->route('incoming.index', ['open' => $item->id])->with('status', __('deals.incoming.flash.added'));
    }

    /** Website form posts here (with the shared token). */
    public function intake(Request $request): JsonResponse
    {
        $token = (string) config('services.intake.token');
        abort_unless($token !== '' && hash_equals($token, (string) $request->header('X-Intake-Token')), 403);
        $d = $request->validate([
            'subject' => ['required', 'string', 'max:200'],
            'message' => ['nullable', 'string', 'max:10000'],
            'name' => ['nullable', 'string', 'max:160'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:160'],
            'module' => ['nullable', Rule::in(Deal::MODULES)],
            'channel' => ['nullable', Rule::in(IncomingMessage::CHANNELS)],
        ]);
        $item = new IncomingMessage([
            'channel' => $d['channel'] ?? 'website', 'subject' => $d['subject'], 'body' => $d['message'] ?? null,
            'from_name' => $d['name'] ?? null, 'from_email' => $d['email'] ?? null, 'from_phone' => $d['phone'] ?? null,
            'company_name' => $d['company'] ?? null, 'module' => $d['module'] ?? null, 'received_at' => now(),
        ]);
        $item->matchKnown();
        $item->save();

        return response()->json(['ok' => true, 'id' => $item->id], 201);
    }

    /** Make a new deal: with a known company, a new company, or a private person. */
    public function convert(Request $request, IncomingMessage $item): RedirectResponse
    {
        abort_unless(in_array($item->status, ['new', 'asked'], true), 404);
        $d = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'module' => ['required', Rule::in(Deal::MODULES)],
            'owner_id' => ['nullable', 'integer', 'exists:users,id'],
            'value' => ['nullable', 'numeric', 'min:0'],
            'company' => ['required', 'string'],          // existing id, "new" or "private"
            'new_company' => [Rule::requiredIf($request->input('company') === 'new'), 'nullable', 'string', 'max:160'],
        ]);

        $deal = DB::transaction(function () use ($item, $d, $request) {
            $company = match ($d['company']) {
                'private' => null,
                'new' => Company::create(['name' => $d['new_company'], 'email' => $item->from_email, 'phone' => $item->from_phone, 'is_customer' => true, 'is_supplier' => false, 'vat_regime' => 'auto', 'language' => $request->user()->locale ?? 'nl']),
                default => Company::findOrFail((int) $d['company']),
            };
            $contact = $item->contact;
            if (! $contact && ($item->from_name || $item->from_email)) {
                [$first, $last] = array_pad(explode(' ', trim((string) $item->from_name), 2), 2, null);
                $contact = $item->from_email ? Contact::where('email', Str::lower($item->from_email))->first() : null;
                $contact ??= Contact::create([
                    'first_name' => $first ?: Str::before((string) $item->from_email, '@'), 'last_name' => $last,
                    'email' => $item->from_email ? Str::lower($item->from_email) : null, 'phone' => $item->from_phone,
                ]);
            }
            if ($company && $contact && ! $company->contacts()->whereKey($contact->id)->exists()) {
                $company->contacts()->attach($contact->id);
            }
            if ($company && ! $company->is_customer) {
                $company->forceFill(['is_customer' => true])->save(); // a supplier asking for something becomes "both"
            }
            $deal = Deal::create([
                'number' => Deal::nextNumber(), 'title' => $d['title'], 'module' => $d['module'], 'stage' => 'new',
                'source' => in_array($item->channel, Deal::SOURCES, true) ? $item->channel : 'other',
                'company_id' => $company?->id, 'contact_id' => $contact?->id, 'owner_id' => $d['owner_id'] ?? $request->user()->id,
                'value_cents' => (int) round(((float) ($d['value'] ?? 0)) * 100), 'incoming_id' => $item->id,
            ]);
            $deal->log('system', __('deals.log.from_incoming', ['channel' => __('deals.sources.'.$deal->source)]));
            $deal->log('note', trim($item->subject."\n\n".$item->body));
            $item->update(['status' => 'converted', 'deal_id' => $deal->id, 'company_id' => $company?->id, 'contact_id' => $contact?->id, 'handled_by_id' => $request->user()->id, 'handled_at' => now()]);

            return $deal;
        });

        return redirect()->route('deals.show', $deal)->with('status', __('deals.flash.created', ['number' => $deal->number]));
    }

    /** Add it to a deal that already exists (e.g. an extra question from the same customer). */
    public function link(Request $request, IncomingMessage $item): RedirectResponse
    {
        abort_unless(in_array($item->status, ['new', 'asked'], true), 404);
        $deal = Deal::findOrFail($request->validate(['deal_id' => ['required', 'integer', 'exists:deals,id']])['deal_id']);
        $deal->log('note', __('deals.log.linked', ['channel' => __('deals.sources.'.(in_array($item->channel, Deal::SOURCES, true) ? $item->channel : 'other'))])."\n".trim($item->subject."\n\n".$item->body));
        $item->update(['status' => 'linked', 'deal_id' => $deal->id, 'handled_by_id' => $request->user()->id, 'handled_at' => now()]);

        return redirect()->route('deals.show', $deal)->with('status', __('deals.incoming.flash.linked', ['number' => $deal->number]));
    }

    /** We asked the sender for details (name, email, date…); it stays in the list until the answer comes. */
    public function ask(Request $request, IncomingMessage $item): RedirectResponse
    {
        abort_unless($item->status === 'new', 404);
        $note = $request->validate(['note' => ['required', 'string', 'max:500']])['note'];
        $item->update(['status' => 'asked', 'handled_note' => $note, 'handled_by_id' => $request->user()->id, 'handled_at' => now()]);

        return back()->with('status', __('deals.incoming.flash.asked'));
    }

    public function dismiss(Request $request, IncomingMessage $item): RedirectResponse
    {
        abort_unless(in_array($item->status, ['new', 'asked'], true), 404);
        $note = $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null;
        $item->update(['status' => 'dismissed', 'handled_note' => $note, 'handled_by_id' => $request->user()->id, 'handled_at' => now()]);

        return redirect()->route('incoming.index')->with('status', __('deals.incoming.flash.dismissed'));
    }
}
