<?php

// Customer card › Credit check (KBO/BCE register, VIES, our own payments).
return [
    'title' => 'Contrôle de crédit',
    'levels' => [
        'green' => 'Aucun signal d’alerte',
        'orange' => 'Prudence',
        'red' => 'Paiement anticipé conseillé',
        'unknown' => 'Pas assez d’informations',
    ],
    'advice' => [
        'green' => 'délai de paiement normal',
        'orange' => 'vérifiez avant d’accorder du crédit ou une grosse commande',
        'red' => 'demandez un paiement anticipé ou un acompte',
        'unknown' => 'pas encore de données du registre ni d’historique de paiement',
    ],
    'no_signs' => 'Le registre, le numéro de TVA et la façon dont ils nous paient ne montrent rien d’inhabituel.',
    'reasons' => [
        'not_found' => 'Le numéro d’entreprise :number n’est pas dans la BCE.',
        'situation' => 'BCE : :what.',
        'situation_other' => 'La BCE indique une situation particulière : :what.',
        'stopped' => 'BCE : l’entreprise est arrêtée.',
        'young' => 'Jeune entreprise : début le :date.',
        'vies_invalid' => 'Le numéro de TVA n’est pas valide (VIES).',
        'vies_invalid_registered' => 'Pas assujetti à la TVA selon VIES (l’entreprise elle-même est active) : vérifiez la TVA sur les factures.',
        'overdue' => ':n facture(s) échue(s), jusqu’à :days jours.',
        'paid_late' => ':n des :of dernières factures payées en retard, en moyenne :days jours.',
    ],
    'reg' => [
        'name' => 'Dénomination officielle',
        'form' => 'Forme juridique',
        'status' => 'Statut',
        'active' => 'Active',
        'stopped' => 'Arrêtée',
        'since' => 'Depuis',
        'years' => '{0} moins d’un an|{1} 1 an|[2,*] :n ans',
        'address' => 'Siège',
        'source' => 'BCE du :date',
        'open' => 'Ouvrir dans la BCE',
        'pending' => 'Les données du registre arrivent avec la prochaine mise à jour BCE.',
        'not_reached' => 'La BCE n’a pas encore répondu ; un nouvel essai se fait automatiquement.',
        'not_belgian' => 'Données du registre uniquement pour les entreprises belges ; VIES et notre historique de paiement comptent quand même.',
    ],
    'refresh' => 'Vérifier maintenant dans la BCE',
    'flash' => [
        'refreshed' => 'Données du registre vérifiées dans la BCE.',
        'not_reached' => 'La BCE n’était pas joignable. Réessayez plus tard.',
    ],
    'nbb_later' => 'Les comptes annuels et le graphique de croissance suivront dès que la connexion avec la Banque nationale sera active.',
    'mail' => [
        'subject' => 'Alerte crédit : :name',
        'intro' => 'Le contrôle de crédit de :name est passé au rouge.',
        'reason' => 'Raison',
        'next' => 'Demandez un paiement anticipé ou un acompte sur les nouvelles commandes jusqu’à clarification.',
        'button' => 'Ouvrir la fiche client',
    ],
];
