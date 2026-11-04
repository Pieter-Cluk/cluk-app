<?php

// Afrit 6 member help: titles, intros and the steps of each video (same order as config/afrit6-help.php).
return [
    'title' => 'Aide',
    'intro' => 'De courtes vidéos qui montrent comment tout faire dans votre portail Afrit 6. Choisissez ce que vous voulez faire.',
    'steps_title' => 'Étape par étape',
    'watch' => 'Voir la vidéo',
    'back' => 'Toute l’aide',
    'video_note' => 'Les vidéos sont en anglais ; les étapes ci-dessous sont en français.',
    'ask' => 'Vous ne trouvez pas ? Demandez à Clukie, en bas à droite.',
    'outro' => '',
    'topics' => [
        'tour' => [
            'title' => 'Votre portail en une minute',
            'intro' => 'Ce que vous trouvez où dans votre portail Afrit 6.',
            'steps' => [
                'Bienvenue dans votre portail Afrit 6. Vous y gérez tout pour votre poste de travail.',
                'En haut se trouvent les onglets : Bureaux, Salle de réunion, Invités, Services entrepôt et Ma facture.',
                'Votre code de porte est toujours sur l’aperçu. Cliquez sur Afficher pour le voir.',
                'Ici, vous voyez vos propres bureaux.',
                'Et vos prochaines réservations de la salle, avec vos moments gratuits de la semaine.',
                'En bas, vous voyez ce que vous avez déjà utilisé ce mois-ci.',
                'Des questions ? Sous Aide, vous trouvez toutes les vidéos, et Clukie vous aide en bas à droite.',
            ],
        ],
        'desk_day' => [
            'title' => 'Un bureau pour une demi-journée ou une journée',
            'intro' => 'Comment réserver un bureau libre pour une matinée, un après-midi ou une journée.',
            'steps' => [
                'Allez dans l’onglet Bureaux.',
                'En haut, vous réservez un bureau pour une matinée, un après-midi ou une journée.',
                'Choisissez la date et la partie de la journée.',
                'Cliquez sur Voir les bureaux libres.',
                'Cliquez sur un bureau libre. Il est réservé tout de suite.',
                'Votre réservation apparaît en dessous. Tant qu’elle n’a pas commencé, vous pouvez l’annuler.',
            ],
        ],
        'fixed_desk' => [
            'title' => 'Votre bureau fixe : changer ou en ajouter un',
            'intro' => 'Comment changer de bureau ou demander un bureau fixe supplémentaire.',
            'steps' => [
                'Sous Bureaux fixes par mois, vous voyez tous les bureaux.',
                'Le vert est votre bureau. Le gris est loué, un pointillé signifie libre.',
                'Cliquez sur un bureau libre.',
                'Vous voulez déménager ? Cliquez sur Changer. Dès demain, vous y êtes, au même prix.',
                'Ou demandez-le comme bureau supplémentaire : choisissez à partir de quand et envoyez la demande.',
                'Vous préférez votre propre bureau ? Demandez un prix sous Bureau privé.',
            ],
        ],
        'room' => [
            'title' => 'Réserver la salle de réunion',
            'intro' => 'Comment réserver la salle par demi-heure.',
            'steps' => [
                'Chaque semaine, vous avez par bureau un moment gratuit de 2 heures maximum.',
                'Choisissez le jour en haut.',
                'Le planning du jour montre les moments libres.',
                'Choisissez la disposition : réunion ou classe.',
                'Choisissez l’heure de début.',
                'Choisissez la durée.',
                'Cliquez sur Réserver.',
                'Votre réservation apparaît sous Vos réservations. Vous pouvez aussi l’annuler là.',
            ],
        ],
        'guests' => [
            'title' => 'Annoncer vos invités',
            'intro' => 'Comment nous dire qui vient vous rendre visite.',
            'steps' => [
                'Allez dans l’onglet Invités.',
                'Choisissez la date et l’heure de la visite.',
                'Tapez le nom de votre invité. Plusieurs invités ? Un nom par ligne.',
                'Ajoutez la société si vous voulez.',
                'Cochez si le nom peut apparaître sur l’écran d’accueil à l’entrée.',
                'Cliquez sur Ajouter.',
                'Vos invités apparaissent sous Invités attendus.',
            ],
        ],
        'storage' => [
            'title' => 'Stockage : une étagère ou un emplacement palette',
            'intro' => 'Comment demander du stockage dans l’entrepôt.',
            'steps' => [
                'Allez dans Services entrepôt. À droite, vous lancez une nouvelle demande.',
                'Choisissez Étagères. Pour des choses plus grandes, choisissez Emplacements palette.',
                'Indiquez combien il vous en faut et à partir de quand.',
                'Ajoutez une remarque si vous voulez.',
                'Cliquez sur Envoyer la demande. L’équipe Afrit 6 confirme votre demande.',
                'Sous Vos demandes, vous suivez tout. Vous payez par jour utilisé et arrêtez la location ici vous-même.',
            ],
        ],
        'parcels' => [
            'title' => 'Recevoir et envoyer des colis',
            'intro' => 'Comment nous prévenir que des marchandises arrivent ou partent.',
            'steps' => [
                'Dans Services entrepôt, ouvrez Réception de marchandises.',
                'Indiquez combien de colis ou de palettes arrivent, et quand.',
                'Choisissez : sur l’étagère de retrait 24 h, ou directement dans votre stockage.',
                'Cliquez sur Envoyer la demande. L’équipe réceptionne tout pour vous.',
                'Vous envoyez quelque chose ? Utilisez Envoi de marchandises et indiquez le transporteur.',
                'Nous pouvons aussi emballer pour vous : choisissez Emballage.',
            ],
        ],
        'door_bill' => [
            'title' => 'Votre code de porte et votre facture',
            'intro' => 'Où trouver votre code de porte et votre facture en cours.',
            'steps' => [
                'Sur l’aperçu, cliquez sur Afficher à côté de Code de porte.',
                'Besoin d’un nouveau code ? Demandez-le ici.',
                'Allez dans l’onglet Ma facture.',
                'Vous y voyez la facture en cours de ce mois de contrat, hors TVA.',
                'En dessous se trouvent vos mois précédents.',
            ],
        ],
    ],
];
