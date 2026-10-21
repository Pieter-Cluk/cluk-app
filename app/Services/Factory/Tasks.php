<?php

namespace App\Services\Factory;

use App\Models\AuditLog;
use App\Models\Factory\Booking;
use App\Models\Factory\Task;
use App\Models\User;
use Illuminate\Support\Carbon;

/** The crew tasks of a Cluk Factory booking. */
class Tasks
{
    /** When a standard task is due for this booking. */
    public static function dueFor(Booking $b, string $day, string $time): Carbon
    {
        $date = match ($day) {
            'first' => $b->firstDay(),
            'start' => $b->start_date->copy(),
            'after' => $b->lastEventDay()->addDay(),
            default => $b->lastDay()->addDay(),
        };
        [$h, $m] = array_map('intval', explode(':', $time));

        return $date->setTime($h, $m);
    }

    /**
     * Make the standard tasks match the booking: add the missing ones, move the dates
     * of the open ones and drop open ones that no longer apply (e.g. the level changed).
     * Done tasks and tasks added by hand are left alone.
     */
    public static function sync(Booking $b): void
    {
        if (! in_array($b->status, ['confirmed', 'done'], true)) {
            return;
        }
        $have = $b->tasks()->whereNotNull('kind')->get()->keyBy('kind');
        foreach (config('factory.tasks') as $kind => [$levels, $day, $time]) {
            $task = $have->get($kind);
            if (! in_array($b->level, $levels, true)) {
                if ($task && ! $task->isDone()) {
                    $task->delete();
                }

                continue;
            }
            $due = self::dueFor($b, $day, $time);
            if (! $task) {
                $b->tasks()->create(['kind' => $kind, 'due_at' => $due]);
            } elseif (! $task->isDone() && ! $task->due_at->equalTo($due)) {
                $task->update(['due_at' => $due]);
            }
        }
    }

    /** A cancelled booking needs no crew: open tasks go. */
    public static function dropOpen(Booking $b): void
    {
        $b->tasks()->whereNull('done_at')->delete();
    }

    public static function add(Booking $b, string $title, Carbon $due, ?User $to, User $by): Task
    {
        $task = $b->tasks()->create(['title' => $title, 'due_at' => $due, 'assignee_id' => $to?->id]);
        AuditLog::record('factory.task_added', $b, ['task' => $title, 'to' => $to?->name], $by);

        return $task;
    }

    public static function assign(Task $task, User $to, User $by): void
    {
        $from = $task->assignee?->name;
        $task->update(['assignee_id' => $to->id]);
        AuditLog::record('factory.task_assigned', $task->booking, ['task' => $task->label(), 'from' => $from, 'to' => $to->name], $by);
    }

    public static function done(Task $task, User $by, ?string $note = null): void
    {
        $task->update(['done_at' => now(), 'done_by_id' => $by->id, 'note' => $note ?: $task->note]);
        AuditLog::record('factory.task_done', $task->booking, ['task' => $task->label()], $by);
    }

    public static function reopen(Task $task, User $by): void
    {
        $task->update(['done_at' => null, 'done_by_id' => null]);
        AuditLog::record('factory.task_reopened', $task->booking, ['task' => $task->label()], $by);
    }
}
