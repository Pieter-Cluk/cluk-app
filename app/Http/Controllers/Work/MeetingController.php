<?php

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\User;
use App\Models\Work\Meeting;
use App\Models\Work\Todo;
use App\Services\Media\Translator;
use App\Services\Work\MeetingAnalyser;
use App\Services\Work\Todos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Meeting notes: paste the text, check the proposed tasks, hand them out. */
class MeetingController extends Controller
{
    public function index(Request $request): View
    {
        $u = $request->user();
        $q = trim((string) $request->query('q', ''));
        $meetings = Meeting::visibleTo($u)->with(['company', 'createdBy'])->withCount(['todos', 'todos as open_count' => fn ($t) => $t->where('status', 'open')])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('title', 'like', "%$q%")->orWhereHas('company', fn ($c) => $c->where('name', 'like', "%$q%"))))
            ->orderByDesc('held_on')->orderByDesc('id')->paginate(30)->withQueryString();

        return view('work.meetings.index', ['meetings' => $meetings, 'q' => $q]);
    }

    public function create(Request $request): View
    {
        $company = $request->integer('company') ? Company::find($request->integer('company')) : null;

        return view('work.meetings.create', ['staff' => self::staff(), 'company' => $company, 'claude' => Translator::available()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $d = $this->validated($request);
        $m = Meeting::create($d + ['created_by_id' => $request->user()->id]);
        AuditLog::record('meeting.created', $m, ['title' => $m->title], $request->user());
        $this->analyse($m);

        return redirect()->route('meetings.show', $m)->with('status', $m->proposals ? trans_choice('meetings.flash.found', count($m->proposals), ['n' => count($m->proposals)]) : __('meetings.flash.none_found'));
    }

    public function show(Request $request, Meeting $meeting): View
    {
        abort_unless($meeting->canBeSeenBy($request->user()), 403);
        $meeting->load(['company', 'createdBy', 'todos.assignee']);

        return view('work.meetings.show', ['m' => $meeting, 'staff' => self::staff(), 'canEdit' => $meeting->canBeEditedBy($request->user()), 'claude' => Translator::available()]);
    }

    /** Change the details or the notes; changed notes are read again. */
    public function update(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless($meeting->canBeEditedBy($request->user()), 403);
        $d = $this->validated($request);
        $again = $d['notes'] !== $meeting->notes || $request->boolean('again');
        $meeting->update($d);
        if ($again) {
            $this->analyse($meeting);
        }

        return redirect()->route('meetings.show', $meeting)->with('status', $again ? trans_choice('meetings.flash.found', count($meeting->proposals ?? []), ['n' => count($meeting->proposals ?? [])]) : __('meetings.flash.saved'));
    }

    /** The checked proposals become tasks of the chosen people. */
    public function makeTasks(Request $request, Meeting $meeting): RedirectResponse
    {
        $me = $request->user();
        abort_unless($meeting->canBeEditedBy($me), 403);
        $d = $request->validate([
            'tasks' => ['required', 'array'],
            'tasks.*.take' => ['nullable', 'boolean'],
            'tasks.*.title' => ['required_if:tasks.*.take,1', 'nullable', 'string', 'max:250'],
            'tasks.*.details' => ['nullable', 'string', 'max:1000'],
            'tasks.*.user_id' => ['required_if:tasks.*.take,1', 'nullable', 'integer', Rule::exists('users', 'id')->where('status', User::STATUS_ACTIVE)],
            'tasks.*.due' => ['nullable', 'date'],
        ]);
        $made = DB::transaction(function () use ($d, $meeting, $me) {
            $made = collect();
            foreach ($d['tasks'] as $t) {
                if (empty($t['take'])) {
                    continue;
                }
                $made->push(Todo::create([
                    'title' => $t['title'], 'details' => $t['details'] ?? null, 'assignee_id' => (int) $t['user_id'], 'created_by_id' => $me->id,
                    'due_on' => $t['due'] ?? null, 'meeting_id' => $meeting->id, 'company_id' => $meeting->company_id,
                ]));
            }
            $meeting->update(['proposals' => null]);
            AuditLog::record('meeting.tasks', $meeting, ['n' => $made->count(), 'to' => $made->map(fn ($t) => $t->assignee?->name)->unique()->implode(', ')], $me);

            return $made;
        });
        Todos::notify($made, $me);

        return redirect()->route('meetings.show', $meeting)->with('status', trans_choice('meetings.flash.made', $made->count(), ['n' => $made->count()]));
    }

    /** Throw the proposals away (nothing to hand out). */
    public function dismiss(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless($meeting->canBeEditedBy($request->user()), 403);
        $meeting->update(['proposals' => null]);

        return redirect()->route('meetings.show', $meeting);
    }

    public function destroy(Request $request, Meeting $meeting): RedirectResponse
    {
        abort_unless($meeting->created_by_id === $request->user()->id || $request->user()->hasRight('meetings.see_all'), 403);
        AuditLog::record('meeting.deleted', null, ['title' => $meeting->title, 'date' => $meeting->held_on->format('d/m/Y')], $request->user());
        $meeting->delete(); // its tasks stay, without the link

        return redirect()->route('meetings.index')->with('status', __('meetings.flash.deleted'));
    }

    private function analyse(Meeting $m): void
    {
        $r = MeetingAnalyser::analyse($m);
        $m->update($r === null ? ['proposals' => []] : [
            'summary' => $r['summary'] ?? $m->summary, 'decisions' => $r['decisions'] ?: $m->decisions, 'proposals' => $r['tasks'],
            'analysed_by' => $r['by'], 'analysed_at' => now(),
        ]);
    }

    private function validated(Request $request): array
    {
        $d = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'held_on' => ['required', 'date'],
            'kind' => ['required', Rule::in(Meeting::KINDS)],
            'company_id' => ['nullable', 'required_if:kind,customer', 'integer', 'exists:companies,id'],
            'attendees' => ['nullable', 'array'],
            'attendees.*' => ['integer', 'exists:users,id'],
            'notes' => ['required', 'string', 'max:200000'],
        ]);
        $d['attendees'] = array_values(array_map('intval', $d['attendees'] ?? []));
        if ($d['kind'] === 'internal') {
            $d['company_id'] = null;
        }

        return $d;
    }

    private static function staff()
    {
        return User::where('status', User::STATUS_ACTIVE)->orderBy('first_name')->get(['id', 'first_name', 'last_name', 'title']);
    }
}
