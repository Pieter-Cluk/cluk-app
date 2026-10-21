<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use App\Models\Afrit6\DeskRental;
use App\Models\Afrit6\RequestMessage;
use App\Models\Afrit6\ServiceRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Afrit6\MemberMails;
use App\Services\Afrit6\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Afrit 6 tasks for staff: member requests, room resets and desk swaps.
 *
 * Rule from the mockups: a task can be handed to someone with the same role
 * and job title, or to someone who reports to you; only the person who has
 * the task (or someone above them) can pass it on, and only the person who
 * has it marks it done.
 */
class TaskController extends Controller
{
    public const FILTERS = ['open', 'pending', 'waiting', 'overdue', 'done', 'all'];

    public function index(Request $request): View
    {
        $me = $request->user();
        $filter = in_array($request->query('status'), self::FILTERS, true) ? $request->query('status') : 'open';
        $who = $request->query('who') === 'mine' ? 'mine' : 'all';

        $base = ServiceRequest::query()->when($who === 'mine', fn ($q) => $q->where('assignee_id', $me->id));
        $apply = function ($q, string $f) {
            return match ($f) {
                'open' => $q->open(),
                'pending' => $q->where('status', ServiceRequest::PENDING),
                'waiting' => $q->where('status', ServiceRequest::WAITING),
                'overdue' => $q->open()->whereNotIn('kind', ServiceRequest::STORAGE)->where('date', '<', today()->toDateString()),
                'done' => $q->whereIn('status', [ServiceRequest::DONE, ServiceRequest::DECLINED]),
                default => $q,
            };
        };
        $counts = [];
        foreach (self::FILTERS as $f) {
            $counts[$f] = $apply(clone $base, $f)->count();
        }

        $tasks = $apply(clone $base, $filter)
            ->with(['member.company', 'assignee', 'space', 'fromSpace', 'booking'])
            ->orderByRaw($filter === 'done' ? 'updated_at desc' : "case when status = 'pending' then 0 when status = 'waiting' then 1 else 2 end, date asc")
            ->paginate(50)->withQueryString();

        return view('afrit6.staff.tasks', compact('tasks', 'counts', 'filter', 'who'));
    }

    public function show(Request $request, ServiceRequest $task): View
    {
        $task->load(['member.company', 'assignee', 'decidedBy', 'space', 'fromSpace', 'booking', 'messages.user', 'messages.member']);
        $me = $request->user();

        return view('afrit6.staff.task', [
            'task' => $task,
            'canAct' => $this->canAct($me, $task),
            'candidates' => $this->candidates($me, $task),
            'activity' => AuditLog::with('actor')->where('subject_type', $task->getMorphClass())->where('subject_id', $task->id)->latest('id')->get(),
        ]);
    }

    /** May $me act on the task: unassigned, or it is mine, or I am above the person who has it. */
    private function canAct(User $me, ServiceRequest $task): bool
    {
        return $task->assignee === null || $me->canReassignFrom($task->assignee);
    }

    private function guard(User $me, ServiceRequest $task): void
    {
        abort_unless($this->canAct($me, $task), 403);
    }

    /** People who can use Afrit 6, with whether I may hand the task to them and why. */
    private function candidates(User $me, ServiceRequest $task): array
    {
        return User::with('role', 'moduleOverrides')->where('status', User::STATUS_ACTIVE)->orderBy('first_name')->get()
            ->filter(fn (User $u) => $u->canUseModule('afrit6') && $u->id !== $task->assignee_id)
            ->map(function (User $u) use ($me) {
                $self = $u->is($me);
                $same = ! $self && $me->role_id && $me->role_id === $u->role_id && $me->title && mb_strtolower($me->title) === mb_strtolower((string) $u->title);
                $below = ! $self && $u->reportsToUser($me);

                return [
                    'user' => $u,
                    'ok' => $me->canHandTaskTo($u),
                    'why' => $self ? 'self' : ($same && $below ? 'same_below' : ($same ? 'same' : ($below ? 'below' : 'no'))),
                ];
            })
            ->sortByDesc('ok')->values()->all();
    }

    public function take(Request $request, ServiceRequest $task): RedirectResponse
    {
        $me = $request->user();
        $this->guard($me, $task);
        $this->assign($task, $me, $me);

        return back()->with('status', __('workspace.tasks.taken'));
    }

    public function reassign(Request $request, ServiceRequest $task): RedirectResponse
    {
        $me = $request->user();
        $this->guard($me, $task);
        $data = $request->validate(['assignee_id' => ['required', 'integer', 'exists:users,id'], 'note' => ['nullable', 'string', 'max:1000']]);
        $target = User::findOrFail($data['assignee_id']);
        if (! $me->canHandTaskTo($target) || ! $target->canUseModule('afrit6')) {
            throw ValidationException::withMessages(['assignee_id' => __('workspace.tasks.cannot_hand', ['name' => $target->name])]);
        }
        $this->assign($task, $target, $me, $data['note'] ?? null);

        return back()->with('status', __('workspace.tasks.handed', ['name' => $target->name]));
    }

    private function assign(ServiceRequest $task, User $to, User $by, ?string $note = null): void
    {
        $from = $task->assignee;
        $task->update(['assignee_id' => $to->id]);
        AuditLog::record('afrit6.task_assigned', $task, array_filter(['from' => $from?->name, 'to' => $to->name, 'note' => $note]), $by);
    }

    /** Confirm a new request. An extra desk is booked straight away. */
    public function confirm(Request $request, ServiceRequest $task): RedirectResponse
    {
        $me = $request->user();
        $this->guard($me, $task);
        abort_unless($task->status === ServiceRequest::PENDING, 404);

        DB::transaction(function () use ($task, $me) {
            $status = ServiceRequest::CONFIRMED;
            if ($task->kind === 'desk_add') {
                if (! $task->space->isFree($task->date, $task->ends_on)) {
                    throw ValidationException::withMessages(['task' => __('workspace.desks.not_free', ['desk' => $task->space->code])]);
                }
                DeskRental::create([
                    'member_id' => $task->member_id, 'space_id' => $task->space_id, 'starts_on' => $task->date,
                    'ends_on' => $task->ends_on, 'monthly_price_cents' => Pricing::price('desk_month'), 'created_by_id' => $me->id,
                ]);
                $status = ServiceRequest::DONE;
            }
            $task->update([
                'status' => $status, 'decided_by_id' => $me->id, 'assignee_id' => $task->assignee_id ?? $me->id,
                'done_at' => $status === ServiceRequest::DONE ? now() : null,
            ]);
            AuditLog::record('afrit6.task_confirmed', $task, [], $me);
        });

        if (in_array($task->kind, config('afrit6.member_kinds'), true)) {
            MemberMails::confirmed($task->fresh());
        }

        return back()->with('status', __('workspace.tasks.confirmed'));
    }

    public function decline(Request $request, ServiceRequest $task): RedirectResponse
    {
        $me = $request->user();
        $this->guard($me, $task);
        abort_unless($task->isOpen(), 404);
        $data = $request->validate(['reason' => ['required', 'string', 'max:500']]);

        DB::transaction(function () use ($task, $me, $data) {
            $task->update(['status' => ServiceRequest::DECLINED, 'status_before_wait' => null, 'decline_reason' => $data['reason'], 'decided_by_id' => $me->id]);
            RequestMessage::create(['request_id' => $task->id, 'user_id' => $me->id, 'body' => $data['reason']]);
            AuditLog::record('afrit6.task_declined', $task, ['reason' => $data['reason']], $me);
        });

        if (in_array($task->kind, config('afrit6.member_kinds'), true)) {
            MemberMails::declined($task->fresh());
        }

        return back()->with('status', __('workspace.tasks.declined'));
    }

    /** Only the person who has the task marks it done. */
    public function done(Request $request, ServiceRequest $task): RedirectResponse
    {
        $me = $request->user();
        abort_unless($task->assignee_id === $me->id, 403);
        if ($task->status !== ServiceRequest::CONFIRMED) {
            throw ValidationException::withMessages(['task' => __('workspace.tasks.confirm_first')]);
        }
        $task->update(['status' => ServiceRequest::DONE, 'done_at' => now()]);
        AuditLog::record('afrit6.task_done', $task, [], $me);

        return back()->with('status', __('workspace.tasks.marked_done'));
    }

    public function reopen(Request $request, ServiceRequest $task): RedirectResponse
    {
        $me = $request->user();
        $this->guard($me, $task);
        abort_unless(in_array($task->status, [ServiceRequest::DONE, ServiceRequest::DECLINED], true) && $task->kind !== 'desk_add', 404);
        $task->update(['status' => $task->decided_by_id ? ServiceRequest::CONFIRMED : ServiceRequest::PENDING, 'done_at' => null, 'decline_reason' => null]);
        AuditLog::record('afrit6.task_reopened', $task, [], $me);

        return back()->with('status', __('workspace.tasks.reopened'));
    }

    /** Write to the member; "ask" puts the task on hold until the member answers. */
    public function message(Request $request, ServiceRequest $task): RedirectResponse
    {
        $me = $request->user();
        $data = $request->validate(['body' => ['required', 'string', 'max:2000'], 'wait' => ['nullable', 'boolean']]);
        abort_if($task->kind === 'room_reset' && ! empty($data['wait']), 404);

        DB::transaction(function () use ($task, $me, $data) {
            RequestMessage::create(['request_id' => $task->id, 'user_id' => $me->id, 'body' => $data['body']]);
            if (! empty($data['wait']) && in_array($task->status, [ServiceRequest::PENDING, ServiceRequest::CONFIRMED], true)) {
                $task->update(['status' => ServiceRequest::WAITING, 'status_before_wait' => $task->status]);
            }
        });

        if (in_array($task->kind, config('afrit6.member_kinds'), true)) {
            MemberMails::question($task, $data['body']);
        }

        return back()->with('status', __(! empty($data['wait']) ? 'workspace.tasks.asked' : 'workspace.tasks.message_sent'));
    }

    /** Change the amount of a one-off service (e.g. more colli than announced). */
    public function amount(Request $request, ServiceRequest $task): RedirectResponse
    {
        $me = $request->user();
        $this->guard($me, $task);
        abort_unless(in_array($task->kind, ['packing', 'receive', 'send'], true), 404);
        $data = $request->validate([
            'colis' => ['required', 'integer', 'min:0', 'max:999'],
            'pallets' => ['required', 'integer', 'min:0', 'max:99'],
            'receive_mode' => [Rule::requiredIf($task->kind === 'receive'), 'nullable', Rule::in(['shelf24', 'store'])],
        ]);
        $before = Pricing::eur($task->amount_cents);
        $task->fill(['colis' => $data['colis'], 'pallets' => $data['pallets'], 'receive_mode' => $task->kind === 'receive' ? $data['receive_mode'] : null]);
        $task->amount_cents = Pricing::oneOff($task->only(['kind', 'colis', 'pallets', 'then_send', 'receive_mode']));
        $task->save();
        AuditLog::record('afrit6.task_amount', $task, ['from' => $before, 'to' => Pricing::eur($task->amount_cents)], $me);

        return back()->with('status', __('workspace.tasks.amount_saved'));
    }
}
