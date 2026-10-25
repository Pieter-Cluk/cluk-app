<?php

namespace App\Services\Work;

use App\Models\User;
use App\Models\Work\Todo;
use App\Notifications\Confirmation;
use App\Support\StaffMail;
use Illuminate\Support\Collection;

class Todos
{
    /** Each person gets one email with the tasks someone else gave them. */
    public static function notify(Collection $todos, User $by): void
    {
        foreach ($todos->where('assignee_id', '!=', $by->id)->groupBy('assignee_id') as $list) {
            $to = $list->first()->assignee;
            if (! $to?->isActive()) {
                continue;
            }
            $loc = $to->locale ?: 'en';
            $meeting = $list->first()->meeting;
            StaffMail::send($to, 'task_assigned', new Confirmation(
                subject: trans_choice('todos.mail.subject', $list->count(), ['n' => $list->count(), 'name' => $by->first_name], $loc),
                greeting: __('mails.hello', ['name' => $to->first_name], $loc),
                intro: [$meeting ? __('todos.mail.from_meeting', ['name' => $by->name, 'meeting' => $meeting->title, 'date' => $meeting->held_on->format('d/m/Y')], $loc) : __('todos.mail.from_person', ['name' => $by->name], $loc)],
                next: $list->map(fn (Todo $t) => '• '.$t->title.($t->due_on ? ' — '.__('todos.mail.due', ['date' => $t->due_on->format('d/m/Y')], $loc) : ''))->values()->all(),
                button: [__('todos.mail.button', [], $loc), route('todos.index')],
                signer: $by,
            ));
        }
    }
}
