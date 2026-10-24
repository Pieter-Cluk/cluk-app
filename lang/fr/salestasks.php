<?php

return [
    'title' => 'Tâches commerciales',
    'intro' => 'Créées par l’app et fermées d’elles-mêmes quand la raison disparaît : fiches clients à compléter, offres ouvertes trop longtemps, clients actifs sur le site ou venus via nos réseaux sociaux.',
    'who' => [
        'me' => 'Les miennes',
        'all' => 'Tout le monde',
        'nobody' => 'Sans responsable',
    ],
    'all_levels' => 'Toutes les couleurs',
    'all_kinds' => 'Tous les types',
    'levels' => [
        'red' => 'À faire maintenant',
        'orange' => 'Important',
        'yellow' => 'Attention',
        'blue' => 'Opportunité',
    ],
    'kinds' => [
        'card' => 'Fiche client',
        'quote' => 'Offre ouverte',
        'web' => 'Site & réseaux',
    ],
    'card' => [
        'title' => '— la fiche demande de l’attention',
    ],
    'problems' => [
        'no_email' => 'pas d’e-mail',
        'no_phone' => 'pas de téléphone',
        'no_address' => 'adresse incomplète',
        'no_vat' => 'pas de n° TVA',
        'vat_invalid' => 'n° TVA non valide (VIES)',
        'no_owner' => 'pas de responsable',
        'no_contact' => 'pas de contact',
        'no_contact_email' => 'contacts sans e-mail',
        'credit_red' => 'contrôle crédit rouge',
    ],
    'quote' => [
        'open' => '{0} envoyée aujourd’hui|{1} ouverte depuis 1 jour|[2,*] ouverte depuis :n jours',
        'expired' => 'validité expirée',
    ],
    'web' => [
        'title' => '— actif sur le site',
        'views' => '{1} 1 page|[2,*] :n pages',
        'products' => '{1} 1 produit consulté|[2,*] :n produits consultés',
        'basket' => 'a mis quelque chose dans le panier',
        'searched' => 'a cherché « :words »',
        'via' => 'venu via :where',
    ],
    'call' => 'contact : :name',
    'no_owner' => 'sans responsable',
    'since' => 'depuis le :when',
    'done' => 'Fait',
    'snooze' => 'Plus tard',
    'days' => '{1} demain|[2,*] dans :n jours',
    'empty' => 'Rien à faire ici. Bravo !',
    'legend' => 'Rouge : à faire maintenant (données erronées, crédit rouge, offre de 20+ jours ou expirée) · Orange : important (10+ jours ; données de facturation manquantes avec un travail en cours ; panier sur le site) · Jaune : attention (5+ jours ; données manquantes) · Bleu : opportunité (actif sur le site ou via les réseaux). « Fait » masque une tâche jusqu’à ce que la raison s’aggrave.',
    'flash' => [
        'done' => 'Marquée comme faite.',
        'snoozed' => '{1} De retour demain.|[2,*] De retour dans :n jours.',
    ],
];
