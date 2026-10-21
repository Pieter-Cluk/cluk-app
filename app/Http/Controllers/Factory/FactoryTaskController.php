<?php

namespace App\Http\Controllers\Factory;

use App\Http\Controllers\Controller;
use App\Models\Factory\Booking;
use App\Models\Factory\Task;
use App\Models\User;
use App\Services\Factory\Tasks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

/**
 * Crew tasks of a Cluk Factory booking. Same rule as everywhere: a task goes to yourself,
 * someone with the same role and job title or someone who reports to you; only the person
 * who has it (or someone above them) passes it on, and only the person who has it marks it done.
 */
class FactoryTaskController extends Controller
{
    /** People who can do Factory tasks, for the hand-over list. */
    public static function crew(): Collection
    {
        return User::with('role', 'moduleOverrides')->where('status', User::STATUS_ACTIVE)->orderBy('first_name')->get()
            ->filter(fn (User $u) => $u->canUseModule('factory'))->values();
    }

    public function store(Request $request, Booking $booking): RedirectResponse
    {
        abort_unless(in_array($booking->status, ['confirmed', 'done'], true), 409);
        $d = $request->validate([
            'title' => ['required', 'string', 'max:200'],
            'due_date' => ['required', 'date'],
            'due_time' => ['required', 'date_format:H:i'],
            'assignee_id' => ['nullable', 'integer', 'exists:users,id'],
        ]);
        $me = $request->user();
        $to = isset($d['assignee_id']) ? User::findOrFail($d['assignee_id']) : null;
        if ($to && (! $me->canHandTaskTo($to) || ! $to->canUseModule('factory'))) {
            throw ValidationException::withMessages(['assignee_id' => __('workspace.tasks.cannot_hand', ['name' => $to->name])]);
        }
        Tasks::add($booking, $d['title'], Carbon::parse($d['due_date'].' '.$d['due_time']), $to, $me);

        return back()->with('status', __('factory.task.added'));
    }

    public function take(Request $request, Task $task): RedirectResponse
    {
        $me = $request->user();
        abort_unless($task->canBeMovedBy($me) && $me->canUseModule('factory', 2), 403);
        Tasks::assign($task, $me, $me);

        return back()->with('status', __('factory.task.taken'));
    }

    public function reassign(Request $request, Task $task): RedirectResponse
    {
        $me = $request->user();
        abort_unless($task->canBeMovedBy($me), 403);
        $target = User::findOrFail($request->validate(['assignee_id' => ['required', 'integer', 'exists:users,id']])['assignee_id']);
        if (! $me->canHandTaskTo($target) || ! $target->canUseModule('factory')) {
            throw ValidationException::withMessages(['assignee_id' => __('workspace.tasks.cannot_hand', ['name' => $target->name])]);
        }
        Tasks::assign($task, $target, $me);

        return back()->with('status', __('workspace.tasks.handed', ['name' => $target->name]));
    }

    public function done(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->assignee_id === $request->user()->id && ! $task->isDone(), 403);
        Tasks::done($task, $request->user(), $request->validate(['note' => ['nullable', 'string', 'max:500']])['note'] ?? null);

        return back()->with('status', __('factory.task.done'));
    }

    public function reopen(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->isDone() && $task->canBeMovedBy($request->user()), 403);
        Tasks::reopen($task, $request->user());

        return back()->with('status', __('factory.task.reopened'));
    }

    public function destroy(Request $request, Task $task): RedirectResponse
    {
        abort_unless($task->kind === null && ! $task->isDone(), 409);
        $task->delete();

        return back()->with('status', __('factory.task.removed'));
    }
}
