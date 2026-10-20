<?php

return [
    'modules' => [
        'companies' => 'Bedrijven & contacten',
        'sales' => 'Offertes & verkoop',
        'purchasing' => 'Aankoop',
        'dryhire' => 'Dry-hire',
        'warehouse' => 'Magazijn',
        'factory' => 'Cluk Factory',
        'afrit6' => 'Cluk Workspace (Afrit 6)',
        'preinvoice' => 'Voorfacturatie',
        'settings' => 'Gebruikers & instellingen',
    ],
    'levels' => [
        'none' => 'Geen toegang',
        'view' => 'Bekijken',
        'edit' => 'Bewerken',
        'manage' => 'Beheren',
    ],
    'rights' => [
        'afrit6.approve_members' => 'Nieuwe Afrit 6-leden goedkeuren',
        'afrit6.door_codes' => 'Deurcodes bekijken en wijzigen',
        'companies.see_health' => 'Financiële gezondheid en krediet bekijken',
        'finance.see_margins' => 'Aankoopprijzen en marges bekijken',
        'finance.send_to_okioki' => 'Proforma’s goedkeuren en naar OkiOki sturen',
        'gdpr.export_erase' => 'GDPR-export en wissen',
        'orders.approve' => 'Orders: goedkeuren en orderbevestiging sturen',
        'sales.approve_low_margin' => 'Verkoop: offertes onder 10% marge goedkeuren',
        'sales.edit_items' => 'Verkoopartikelen: toevoegen en bewerken',
        'users.manage' => 'Gebruikers en rollen beheren',
        'warehouse.release_repair' => 'Magazijn: items vrijgeven uit herstelling',
        'warehouse.setup_locations' => 'Magazijn: locaties instellen en labels printen',
        'warehouse.sign_delivery' => 'Magazijn: leveringsbonnen tekenen en overhandigen',
        'warehouse.write_off' => 'Magazijn: verloren of kapotte stock afboeken',
    ],
    'app' => [
        'name' => 'CLUK',
        'tagline' => 'Connecting the dots',
    ],
];
