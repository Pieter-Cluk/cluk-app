<?php

// Tasks for one person (from meetings or by hand).
return [
    'intro' => 'Your tasks from meetings and from colleagues. Tick them off when done.',
    'add_ph' => 'New task…',
    'add' => 'Add',
    'everyone' => 'Everyone',
    'mark_done' => 'Mark as done',
    'reopen' => 'Open again',
    'change' => 'Change',
    'save' => 'Save',
    'due' => 'by :date',
    'late_since' => 'late since :date',
    'from' => 'from :name',
    'done_on' => 'done :date',
    'views' => [
        'mine' => 'Mine',
        'given' => 'Given to others',
        'all' => 'Everyone',
        'done' => 'Done',
    ],
    'empty' => [
        'mine' => 'No open tasks. Nice.',
        'given' => 'No open tasks you gave to others.',
        'all' => 'No open tasks.',
        'done' => 'Nothing done yet.',
    ],
    'fields' => [
        'title' => 'Task',
        'details' => 'More details (optional)',
        'assignee' => 'Who',
        'due' => 'By when',
    ],
    'flash' => [
        'added' => 'Task added.',
        'done' => 'Done. Well done!',
        'reopened' => 'Task open again.',
        'saved' => 'Task saved.',
    ],
    'mail' => [
        'subject' => '{1} New task from :name|[2,*] :n new tasks from :name',
        'from_meeting' => ':name gave you tasks from the meeting “:meeting” of :date.',
        'from_person' => ':name gave you a task.',
        'due' => 'by :date',
        'button' => 'Open my tasks',
    ],
];
