<?php

return [
    'modules' => [
        'companies' => 'Entreprises & contacts',
        'sales' => 'Devis & ventes',
        'purchasing' => 'Achats',
        'dryhire' => 'Location sèche',
        'warehouse' => 'Entrepôt',
        'factory' => 'Cluk Factory',
        'afrit6' => 'Cluk Workspace (Afrit 6)',
        'preinvoice' => 'Préfacturation',
        'settings' => 'Utilisateurs & paramètres',
    ],
    'levels' => [
        'none' => 'Aucun accès',
        'view' => 'Consulter',
        'edit' => 'Modifier',
        'manage' => 'Gérer',
    ],
    'rights' => [
        'afrit6' => [
            'approve_members' => 'Approuver les nouveaux membres Afrit 6',
            'door_codes' => 'Voir et modifier les codes de porte',
        ],
        'companies' => [
            'see_health' => 'Voir la santé financière et le crédit',
        ],
        'finance' => [
            'see_margins' => 'Voir les prix d’achat et les marges',
            'send_to_okioki' => 'Approuver les proformas et les envoyer à OkiOki',
            'vat_treatment' => 'Modifier le traitement TVA d\'une entreprise',
        ],
        'gdpr' => [
            'export_erase' => 'Export et effacement RGPD',
        ],
        'orders' => [
            'approve' => 'Commandes : approuver et envoyer la confirmation',
        ],
        'sales' => [
            'approve_low_margin' => 'Ventes : approuver les devis sous 10 % de marge',
            'edit_items' => 'Articles de vente : ajouter et modifier',
        ],
        'users' => [
            'manage' => 'Gérer les utilisateurs et les rôles',
        ],
        'warehouse' => [
            'release_repair' => 'Entrepôt : libérer les articles de réparation',
            'setup_locations' => 'Entrepôt : configurer les emplacements et imprimer les étiquettes',
            'sign_delivery' => 'Entrepôt : signer les bons de livraison et remettre',
            'write_off' => 'Entrepôt : sortir le stock perdu ou cassé',
        ],
    ],
    'app' => [
        'name' => 'CLUK',
        'tagline' => 'Connecting the dots',
    ],
];
