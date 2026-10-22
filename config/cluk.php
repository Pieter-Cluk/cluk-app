<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Languages
    |--------------------------------------------------------------------------
    | Languages offered in the app and on documents. More follow later
    | (the documents will need 14 languages).
    */

    // Staff without Microsoft 365 sign in with a code sent to an address on this domain.
    'staff_email_domain' => env('STAFF_EMAIL_DOMAIN', 'cluk.live'),

    'locales' => [
        'en' => 'English',
        'nl' => 'Nederlands',
        'fr' => 'Français',
        'es' => 'Español',
    ],

    /*
    |--------------------------------------------------------------------------
    | Modules and access levels
    |--------------------------------------------------------------------------
    | Each role gets a level per module; a person can get a different level
    | for one module (an override). The menu only shows modules with level > 0.
    */

    'levels' => [
        0 => 'none',
        1 => 'view',
        2 => 'edit',
        3 => 'manage',
    ],

    'modules' => [
        'companies',   // Companies & contacts
        'sales',       // Quotes & sales (rental quotes, product sales, deals)
        'purchasing',  // Purchasing and sub-hire
        'dryhire',     // Dry-hire items & assets
        'warehouse',   // Warehouse
        'factory',     // Cluk Factory
        'afrit6',      // Cluk Workspace (Afrit 6)
        'preinvoice',  // Pre-invoicing / finance
        'settings',    // Users & settings
    ],

    /*
    |--------------------------------------------------------------------------
    | Extra rights
    |--------------------------------------------------------------------------
    | Rights on top of the module levels. Given per role, and per person.
    */

    'rights' => [
        'afrit6.approve_members',
        'afrit6.door_codes',
        'companies.see_health',
        'finance.see_margins',
        'finance.send_to_okioki',
        'finance.vat_treatment',
        'gdpr.export_erase',
        'orders.approve',
        'sales.approve_low_margin',
        'sales.edit_items',
        'users.manage',
        'warehouse.release_repair',
        'warehouse.setup_locations',
        'warehouse.sign_delivery',
        'warehouse.write_off',
    ],

];
