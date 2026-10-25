<?php

// Tasks for one person (from meetings or by hand).
return [
    'intro' => 'Vos tâches issues des réunions et des collègues. Cochez-les quand c’est fait.',
    'add_ph' => 'Nouvelle tâche…',
    'add' => 'Ajouter',
    'everyone' => 'Tout le monde',
    'mark_done' => 'Marquer comme fait',
    'reopen' => 'Rouvrir',
    'change' => 'Modifier',
    'save' => 'Enregistrer',
    'due' => 'pour le :date',
    'late_since' => 'en retard depuis le :date',
    'from' => 'de :name',
    'done_on' => 'fait le :date',
    'views' => [
        'mine' => 'Les miennes',
        'given' => 'Données à d’autres',
        'all' => 'Tout le monde',
        'done' => 'Faites',
    ],
    'empty' => [
        'mine' => 'Aucune tâche ouverte. Bravo.',
        'given' => 'Aucune tâche ouverte donnée à d’autres.',
        'all' => 'Aucune tâche ouverte.',
        'done' => 'Rien de fait pour l’instant.',
    ],
    'fields' => [
        'title' => 'Tâche',
        'details' => 'Plus de détails (facultatif)',
        'assignee' => 'Qui',
        'due' => 'Pour quand',
    ],
    'flash' => [
        'added' => 'Tâche ajoutée.',
        'done' => 'Fait. Bravo !',
        'reopened' => 'Tâche rouverte.',
        'saved' => 'Tâche enregistrée.',
    ],
    'mail' => [
        'subject' => '{1} Nouvelle tâche de :name|[2,*] :n nouvelles tâches de :name',
        'from_meeting' => ':name vous a confié des tâches de la réunion « :meeting » du :date.',
        'from_person' => ':name vous a confié une tâche.',
        'due' => 'pour le :date',
        'button' => 'Ouvrir mes tâches',
    ],
];
