<?php

// Tasks for one person (from meetings or by hand).
return [
    'intro' => 'Sus tareas de reuniones y de compañeros. Márquelas cuando estén hechas.',
    'add_ph' => 'Nueva tarea…',
    'add' => 'Añadir',
    'everyone' => 'Todos',
    'mark_done' => 'Marcar como hecha',
    'reopen' => 'Reabrir',
    'change' => 'Cambiar',
    'save' => 'Guardar',
    'due' => 'para el :date',
    'late_since' => 'con retraso desde el :date',
    'from' => 'de :name',
    'done_on' => 'hecha el :date',
    'views' => [
        'mine' => 'Mías',
        'given' => 'Dadas a otros',
        'all' => 'Todos',
        'done' => 'Hechas',
    ],
    'empty' => [
        'mine' => 'No hay tareas abiertas. Genial.',
        'given' => 'No hay tareas abiertas que dio a otros.',
        'all' => 'No hay tareas abiertas.',
        'done' => 'Aún nada hecho.',
    ],
    'fields' => [
        'title' => 'Tarea',
        'details' => 'Más detalles (opcional)',
        'assignee' => 'Quién',
        'due' => 'Para cuándo',
    ],
    'flash' => [
        'added' => 'Tarea añadida.',
        'done' => 'Hecho. ¡Bien!',
        'reopened' => 'Tarea reabierta.',
        'saved' => 'Tarea guardada.',
    ],
    'mail' => [
        'subject' => '{1} Nueva tarea de :name|[2,*] :n nuevas tareas de :name',
        'from_meeting' => ':name le dio tareas de la reunión «:meeting» del :date.',
        'from_person' => ':name le dio una tarea.',
        'due' => 'para el :date',
        'button' => 'Abrir mis tareas',
    ],
];
