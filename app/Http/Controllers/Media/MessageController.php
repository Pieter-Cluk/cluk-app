<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Media\Message;
use App\Services\Media\MailBanners;
use App\Services\Media\Messages;
use App\Services\Media\Translator;
use App\Support\Images;
use App\Support\SafeMail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/** Media › Messages: birthday card, newsletters, promotions, the banner on quotes and the email banners. */
class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $kind = in_array($request->query('kind'), Message::KINDS, true) ? $request->query('kind') : 'newsletter';

        return view('media.messages.index', [
            'kind' => $kind,
            'messages' => Message::kind($kind)->with(['createdBy', 'sentBy'])->when($kind === 'mail_banner', fn ($q) => $q->orderBy('position')->orderBy('id'), fn ($q) => $q->latest('id'))->paginate(30)->withQueryString(),
            'live' => $kind === 'mail_banner' ? MailBanners::live() : collect(),
            'counts' => collect(Message::KINDS)->mapWithKeys(fn ($k) => [$k => Message::kind($k)->count()]),
            'inUse' => in_array($kind, ['birthday', 'banner'], true) ? Message::inUse($kind) : null,
            'canEdit' => $request->user()->canUseModule('sales', 2),
            'audience' => in_array($kind, Message::MAILED, true) ? count(Messages::recipients(new Message(['kind' => $kind]))) : null,
        ]);
    }

    public function create(Request $request): View
    {
        $kind = $request->validate(['kind' => ['required', Rule::in(Message::KINDS)]])['kind'];

        return view('media.messages.form', ['m' => new Message(['kind' => $kind, 'subject' => [], 'body' => []]), 'translatorOn' => Translator::available()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $kind = $request->validate(['kind' => ['required', Rule::in(Message::KINDS)]])['kind'];
        $m = new Message(['kind' => $kind, 'created_by_id' => $request->user()->id, 'status' => 'draft']);
        if ($kind === 'mail_banner') {
            $m->position = (int) Message::kind('mail_banner')->max('position') + 1;
            $m->active = false;
        }
        $this->fill($request, $m);
        AuditLog::record('messages.created', $m, ['kind' => $kind, 'title' => $m->title], $request->user());

        return redirect()->route('messages.edit', $m)->with('status', $this->savedText($m));
    }

    public function edit(Message $message): View
    {
        return view('media.messages.form', ['m' => $message->load(['sends']), 'translatorOn' => Translator::available()]);
    }

    public function update(Request $request, Message $message): RedirectResponse
    {
        abort_unless($message->isEditable(), 409);
        $this->fill($request, $message);

        return back()->with('status', $this->savedText($message));
    }

    private function savedText(Message $m): string
    {
        return __('messages.saved').($m->translationsOutdated() ? ' '.__(Translator::available() ? 'messages.translate_failed' : 'messages.no_translator') : '');
    }

    private function fill(Request $request, Message $m): void
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:160'],
            'subject' => ['array'], 'subject.*' => ['nullable', 'string', 'max:200'],
            'body' => ['array'], 'body.*' => ['nullable', 'string', 'max:10000'],
            'image' => ['nullable', ...Images::rule()],
            'remove_image' => ['boolean'],
            'pdf' => ['nullable', 'file', 'mimes:pdf', 'max:20480'],
            'remove_pdf' => ['boolean'],
            'active_from' => ['nullable', 'date'], 'active_until' => ['nullable', 'date', 'after_or_equal:active_from'],
            'link_url' => ['nullable', 'url:http,https', 'max:500'],
        ]);
        DB::transaction(function () use ($request, $m, $d) {
            $oldHash = $m->exists ? $m->sourceHash() : null;
            $subject = array_intersect_key($d['subject'] ?? [], array_flip(Message::LOCALES));
            $body = array_intersect_key($d['body'] ?? [], array_flip(Message::LOCALES));
            $m->fill(['title' => $d['title'], 'subject' => array_map(fn ($v) => (string) $v, $subject), 'body' => array_map(fn ($v) => (string) $v, $body),
                'active_from' => $d['active_from'] ?? null, 'active_until' => $d['active_until'] ?? null,
                'link_url' => $m->kind === 'mail_banner' ? ($d['link_url'] ?? null) : $m->link_url]);
            // Hand-made translations count as up to date when the English stayed the same.
            if ($oldHash !== null && $oldHash === $m->sourceHash() && $m->source_hash === null && collect(['nl', 'fr', 'es'])->contains(fn ($l) => filled($body[$l] ?? null))) {
                $m->source_hash = $m->sourceHash();
            }
            if ($request->boolean('remove_image') && $m->image_path) {
                Images::delete($m->image_path);
                $m->image_path = null;
            }
            if ($request->hasFile('image')) {
                try {
                    $path = Images::store($request->file('image'), 'messages', 1200);
                } catch (\Throwable) {
                    throw ValidationException::withMessages(['image' => __('pictures.not_image')]);
                }
                Images::delete($m->image_path);
                $m->image_path = $path;
            }
            if ($request->boolean('remove_pdf') && $m->pdf_path) {
                Storage::disk('local')->delete($m->pdf_path);
                $m->pdf_path = $m->pdf_name = null;
            }
            if ($request->hasFile('pdf') && $m->kind === 'newsletter') {
                $file = $request->file('pdf');
                if ($m->pdf_path) {
                    Storage::disk('local')->delete($m->pdf_path);
                }
                $m->pdf_path = $file->store('messages/pdf', 'local');
                $m->pdf_name = mb_substr($file->getClientOriginalName(), 0, 190);
            }
            $m->save();
        });
        if ($m->translationsOutdated()) {
            Messages::translate($m);
        }
    }

    public function translate(Message $message): RedirectResponse
    {
        abort_unless($message->isEditable(), 409);

        return back()->with('status', Messages::translate($message, true) ? __('messages.translated') : __(Translator::available() ? 'messages.translate_failed' : 'messages.no_translator'));
    }

    /** A test mail to yourself, in a language of choice. */
    public function test(Request $request, Message $message): RedirectResponse
    {
        $loc = $request->validate(['locale' => ['required', Rule::in(Message::LOCALES)]])['locale'];
        $me = $request->user();
        $mail = $message->kind === 'mail_banner' ? MailBanners::sample($message, $loc, $me) : Messages::mail($message, $loc, null, ['name' => $me->first_name]);
        $ok = SafeMail::notify(Notification::route('mail', $me->email), $mail);

        return back()->with('status', $ok ? __('messages.test_sent', ['email' => $me->email]) : __('messages.mail_failed'));
    }

    /**
     * Invitation: plan it. From the start day, country by country (the countries in the order given first), at most
     * N people a day, weekdays 9:00–16:00. The list is made on the start day, so customers added until then are in it.
     */
    public function plan(Request $request, Message $message): RedirectResponse
    {
        abort_unless($message->kind === 'invitation', 404);
        $this->readyToSend($message);
        $d = $request->validate([
            'start' => ['required', 'date', 'after_or_equal:today'],
            'per_day' => ['required', 'integer', 'min:10', 'max:500'],
            'countries' => ['nullable', 'string', 'max:200'],
        ]);
        $countries = collect(preg_split('/[\s,;]+/', strtoupper((string) ($d['countries'] ?? ''))))->map(fn ($c) => $c === 'UK' ? 'GB' : $c)
            ->filter(fn ($c) => preg_match('/^[A-Z]{2}$/', $c))->unique()->values()->all() ?: Messages::FIRST_COUNTRIES;
        $start = Carbon::parse($d['start'], 'Europe/Brussels')->setTime(Messages::HOURS[0], 0);
        $message->update(['plan' => ['start' => $start->toDateString(), 'per_day' => (int) $d['per_day'], 'countries' => $countries],
            'status' => 'scheduled', 'send_at' => $start, 'sent_by_id' => $request->user()->id]);
        AuditLog::record('messages.scheduled', $message, ['at' => $start->format('d/m/Y'), 'per_day' => (int) $d['per_day'], 'countries' => implode(', ', $countries)], $request->user());

        return back()->with('status', __('messages.invite.planned', ['date' => $start->format('d/m/Y')]));
    }

    /** Invitation: stop sending for now, or carry on (the days not reached yet keep their date). */
    public function pause(Request $request, Message $message): RedirectResponse
    {
        abort_unless($message->kind === 'invitation' && in_array($message->status, ['sending', 'paused'], true), 409);
        $on = $message->status === 'sending';
        $message->update(['status' => $on ? 'paused' : 'sending']);
        AuditLog::record($on ? 'messages.paused' : 'messages.resumed', $message, ['sent' => $message->sends()->where('status', 'sent')->count()], $request->user());

        return back()->with('status', __($on ? 'messages.invite.paused' : 'messages.invite.resumed'));
    }

    public function send(Request $request, Message $message): RedirectResponse
    {
        abort_if($message->kind === 'invitation', 409); // invitations are always planned
        $this->readyToSend($message);
        $n = Messages::start($message, $request->user());

        return back()->with('status', trans_choice('messages.sending', $n, ['n' => $n]));
    }

    public function schedule(Request $request, Message $message): RedirectResponse
    {
        abort_if($message->kind === 'invitation', 409);
        $this->readyToSend($message);
        $at = Carbon::parse($request->validate(['send_at' => ['required', 'date', 'after:now']])['send_at']);
        $message->update(['status' => 'scheduled', 'send_at' => $at, 'sent_by_id' => $request->user()->id]);
        AuditLog::record('messages.scheduled', $message, ['at' => $at->format('d/m/Y H:i')], $request->user());

        return back()->with('status', __('messages.scheduled', ['at' => $at->format('d/m/Y H:i')]));
    }

    public function unschedule(Request $request, Message $message): RedirectResponse
    {
        abort_unless($message->status === 'scheduled', 409);
        $message->update(['status' => 'draft', 'send_at' => null]);

        return back()->with('status', __('messages.unscheduled'));
    }

    private function readyToSend(Message $m): void
    {
        abort_unless(in_array($m->kind, Message::MAILED, true) && in_array($m->status, ['draft', 'scheduled'], true), 409);
        if (blank($m->subject['en'] ?? null) || (blank($m->body['en'] ?? null) && ! $m->image_path && ! $m->pdf_path)) {
            throw ValidationException::withMessages(['send' => __('messages.errors.empty')]);
        }
        if ($m->kind === 'newsletter' && ! $m->pdf_path && blank($m->body['en'] ?? null)) {
            throw ValidationException::withMessages(['send' => __('messages.errors.empty')]);
        }
    }

    /** Birthday card and quote banner: switch on (only one birthday card at a time) or off. */
    public function toggle(Request $request, Message $message): RedirectResponse
    {
        abort_if(in_array($message->kind, Message::MAILED, true), 409);
        $on = ! $message->active;
        if ($on && $message->kind === 'birthday') {
            Message::kind('birthday')->where('id', '!=', $message->id)->update(['active' => false]);
        }
        $message->update(['active' => $on]);
        AuditLog::record($on ? 'messages.activated' : 'messages.deactivated', $message, ['kind' => $message->kind], $request->user());

        return back()->with('status', __($on ? 'messages.now_on' : 'messages.now_off'));
    }

    /** Email banners: change the order of the rotation. */
    public function move(Request $request, Message $message, string $dir): RedirectResponse
    {
        abort_unless($message->kind === 'mail_banner', 404);
        $list = Message::kind('mail_banner')->orderBy('position')->orderBy('id')->get()->values();
        $i = $list->search(fn ($m) => $m->id === $message->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $list->count()) {
            [$list[$i], $list[$j]] = [$list[$j], $list[$i]];
        }
        foreach ($list as $n => $m) {
            $m->forceFill(['position' => $n + 1])->saveQuietly();
        }
        AuditLog::record('messages.reordered', $message, ['kind' => 'mail_banner', 'order' => $list->pluck('title')->implode(' → ')], $request->user());

        return back();
    }

    public function destroy(Request $request, Message $message): RedirectResponse
    {
        abort_if(in_array($message->status, ['sending', 'sent'], true), 409);
        Images::delete($message->image_path);
        if ($message->pdf_path) {
            Storage::disk('local')->delete($message->pdf_path);
        }
        $kind = $message->kind;
        AuditLog::record('messages.deleted', null, ['title' => $message->title, 'kind' => $kind], $request->user());
        $message->delete();

        return redirect()->route('messages.index', ['kind' => $kind])->with('status', __('messages.deleted'));
    }
}
