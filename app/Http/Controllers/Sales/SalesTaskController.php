<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Sales\SalesTask;
use App\Models\User;
use App\Services\Sales\CommercialTasks;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Commercial tasks made by the app: mine or everyone's, most urgent first. */
class SalesTaskController extends Controller
{
    public static function allowed(User $u): bool
    {
        return $u->canUseModule('sales', 2);
    }

    public function index(Request $request): View
    {
        abort_unless(self::allowed($request->user()), 403);
        CommercialTasks::refreshIfStale();
        $who = in_array($request->query('who'), ['me', 'all'], true) ? $request->query('who') : 'me';
        $kind = in_array($request->query('kind'), SalesTask::KINDS, true) ? $request->query('kind') : null;
        $level = in_array($request->query('level'), SalesTask::LEVELS, true) ? $request->query('level') : null;
        $base = SalesTask::open()
            ->when($who === 'me', fn ($q) => $q->where('owner_id', $request->user()->id));
        $tasks = (clone $base)->with(['company', 'contact', 'owner'])->when($kind, fn ($q) => $q->where('kind', $kind))->when($level, fn ($q) => $q->where('level', $level))
            ->ordered()->paginate(50)->withQueryString();

        return view('sales.tasks', [
            'tasks' => $tasks, 'who' => $who, 'kind' => $kind, 'level' => $level,
            'levels' => (clone $base)->selectRaw('level, count(*) n')->groupBy('level')->pluck('n', 'level'),
            'kinds' => (clone $base)->selectRaw('kind, count(*) n')->groupBy('kind')->pluck('n', 'kind'),
            'counts' => ['me' => SalesTask::open()->where('owner_id', $request->user()->id)->count(), 'all' => SalesTask::open()->count()],
        ]);
    }

    /** Handled (called, sent a reminder…): gone until the reason gets worse. */
    public function done(Request $request, SalesTask $task): RedirectResponse
    {
        abort_unless(self::allowed($request->user()), 403);
        $task->update(['status' => 'done', 'handled_by_id' => $request->user()->id, 'handled_at' => now()]);
        AuditLog::record('sales.task_done', $task->company, ['task' => $task->kind, 'key' => $task->key], $request->user());

        return back()->with('status', __('salestasks.flash.done'));
    }

    /** Not now: back after a few days. */
    public function snooze(Request $request, SalesTask $task): RedirectResponse
    {
        abort_unless(self::allowed($request->user()), 403);
        $days = (int) $request->validate(['days' => ['required', 'in:1,3,7,14']])['days'];
        $task->update(['status' => 'snoozed', 'snoozed_until' => now()->addDays($days)->startOfDay()->setTime(7, 0), 'handled_by_id' => $request->user()->id, 'handled_at' => now()]);

        return back()->with('status', trans_choice('salestasks.flash.snoozed', $days, ['n' => $days]));
    }
}
