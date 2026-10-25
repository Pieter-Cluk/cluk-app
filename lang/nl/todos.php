<?php

// Tasks for one person (from meetings or by hand).
return [
    'intro' => 'Je taken uit vergaderingen en van collega’s. Vink ze af als ze klaar zijn.',
    'add_ph' => 'Nieuwe taak…',
    'add' => 'Toevoegen',
    'everyone' => 'Iedereen',
    'mark_done' => 'Markeer als klaar',
    'reopen' => 'Opnieuw openen',
    'change' => 'Wijzigen',
    'save' => 'Opslaan',
    'due' => 'tegen :date',
    'late_since' => 'te laat sinds :date',
    'from' => 'van :name',
    'done_on' => 'klaar :date',
    'views' => [
        'mine' => 'Van mij',
        'given' => 'Gegeven aan anderen',
        'all' => 'Iedereen',
        'done' => 'Klaar',
    ],
    'empty' => [
        'mine' => 'Geen open taken. Top.',
        'given' => 'Geen open taken die je aan anderen gaf.',
        'all' => 'Geen open taken.',
        'done' => 'Nog niets klaar.',
    ],
    'fields' => [
        'title' => 'Taak',
        'details' => 'Meer uitleg (optioneel)',
        'assignee' => 'Wie',
        'due' => 'Tegen wanneer',
    ],
    'flash' => [
        'added' => 'Taak toegevoegd.',
        'done' => 'Klaar. Goed gedaan!',
        'reopened' => 'Taak opnieuw open.',
        'saved' => 'Taak opgeslagen.',
    ],
    'mail' => [
        'subject' => '{1} Nieuwe taak van :name|[2,*] :n nieuwe taken van :name',
        'from_meeting' => ':name gaf je taken uit de vergadering “:meeting” van :date.',
        'from_person' => ':name gaf je een taak.',
        'due' => 'tegen :date',
        'button' => 'Mijn taken openen',
    ],
];
