<?php

namespace App\Http\Controllers\Work;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\User;
use App\Models\Work\Todo;
use App\Services\Work\Todos;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** Tasks: mine, the ones I gave to others, or everyone's (with the right). */
class TodoController extends Controller
{
    public const VIEWS = ['mine', 'given', 'all', 'done'];

    public function index(Request $request): View
    {
        $u = $request->user();
        $all = $u->hasRight('meetings.see_all');
        $view = in_array($request->query('v'), self::VIEWS, true) && ($request->query('v') !== 'all' || $all) ? $request->query('v') : 'mine';
        $who = $all && $request->integer('user') ? $request->integer('user') : null;
        $q = Todo::with(['assignee', 'createdBy', 'meeting', 'company']);
        match ($view) {
            'mine' => $q->open()->where('assignee_id', $u->id),
            'given' => $q->open()->where('created_by_id', $u->id)->where('assignee_id', '!=', $u->id),
            'all' => $q->open()->when($who, fn ($w) => $w->where('assignee_id', $who)),
            'done' => $q->where('status', 'done')->where(fn ($w) => $w->where('assignee_id', $u->id)->orWhere('created_by_id', $u->id))->latest('done_at'),
        };
        if ($view !== 'done') {
            $q->orderByRaw('due_on is null')->orderBy('due_on')->orderBy('id');
        }

        return view('work.todos.index', [
            'todos' => $q->paginate(60)->withQueryString(), 'view' => $view, 'canAll' => $all, 'who' => $who,
            'counts' => [
                'mine' => Todo::open()->where('assignee_id', $u->id)->count(),
                'given' => Todo::open()->where('created_by_id', $u->id)->where('assignee_id', '!=', $u->id)->count(),
                'all' => $all ? Todo::open()->count() : null,
            ],
            'staff' => User::where('status', User::STATUS_ACTIVE)->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    /** A task added by hand, for myself or a colleague. */
    public function store(Request $request): RedirectResponse
    {
        $me = $request->user();
        $d = $request->validate([
            'title' => ['required', 'string', 'max:250'],
            'assignee_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', User::STATUS_ACTIVE)],
            'due_on' => ['nullable', 'date'],
        ]);
        $t = Todo::create($d + ['created_by_id' => $me->id]);
        Todos::notify(collect([$t]), $me);

        return back()->with('status', __('todos.flash.added'));
    }

    public function done(Request $request, Todo $todo): RedirectResponse
    {
        abort_unless($todo->canBeChangedBy($request->user()), 403);
        $open = $todo->status === 'open';
        $todo->update($open ? ['status' => 'done', 'done_at' => now(), 'done_by_id' => $request->user()->id] : ['status' => 'open', 'done_at' => null, 'done_by_id' => null]);

        return back()->with('status', $open ? __('todos.flash.done') : __('todos.flash.reopened'));
    }

    /** Another person or date. */
    public function update(Request $request, Todo $todo): RedirectResponse
    {
        $me = $request->user();
        abort_unless($todo->canBeChangedBy($me), 403);
        $d = $request->validate([
            'assignee_id' => ['required', 'integer', Rule::exists('users', 'id')->where('status', User::STATUS_ACTIVE)],
            'due_on' => ['nullable', 'date'],
        ]);
        $moved = (int) $d['assignee_id'] !== $todo->assignee_id;
        $todo->update($d);
        if ($moved) {
            AuditLog::record('todo.moved', $todo, ['title' => $todo->title, 'to' => $todo->fresh()->assignee?->name], $me);
            Todos::notify(collect([$todo->fresh()]), $me);
        }

        return back()->with('status', __('todos.flash.saved'));
    }
}
