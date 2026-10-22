<?php

/*
|--------------------------------------------------------------------------
| Sidebar menu
|--------------------------------------------------------------------------
| Each item shows only when the person may use it:
|   'module' => [module, minimum level]   or   'right' => right key
| 'path' is the address; screens that are not built yet show a
| "coming soon" page so the menu already works end to end.
*/

return [
    'top' => [
        ['key' => 'dashboard', 'path' => '/dashboard', 'icon' => 'home'],
        ['key' => 'management', 'path' => '/management', 'icon' => 'chart', 'right' => 'finance.see_margins'],
        ['key' => 'wallscreens', 'path' => '/wall-screens', 'icon' => 'screen', 'module' => ['warehouse', 1]],
    ],

    'groups' => [
        'commercial' => [
            ['key' => 'companies', 'path' => '/companies', 'icon' => 'people', 'module' => ['companies', 1]],
            ['key' => 'sales', 'path' => '/sales', 'icon' => 'doc', 'module' => ['sales', 1]],
            ['key' => 'orders', 'path' => '/order-allocation', 'icon' => 'clipboard', 'module' => ['dryhire', 2]],
            ['key' => 'subhire', 'path' => '/sub-hire', 'icon' => 'swap', 'module' => ['purchasing', 1]],
            ['key' => 'productsales', 'path' => '/product-sales', 'icon' => 'tag', 'module' => ['sales', 1]],
            ['key' => 'purchasing', 'path' => '/purchasing', 'icon' => 'cart', 'module' => ['purchasing', 1]],
        ],
        'operations' => [
            ['key' => 'dryhire', 'path' => '/dry-hire', 'icon' => 'box', 'module' => ['dryhire', 1]],
            ['key' => 'warehouse', 'path' => '/warehouse', 'icon' => 'warehouse', 'module' => ['warehouse', 1]],
            ['key' => 'factory', 'path' => '/factory', 'icon' => 'stage', 'module' => ['factory', 1]],
            ['key' => 'afrit6', 'path' => '/afrit6-staff', 'icon' => 'desk', 'module' => ['afrit6', 1]],
        ],
        'media' => [
            ['key' => 'messages', 'path' => '/media/messages', 'icon' => 'message', 'module' => ['sales', 1]],
            ['key' => 'social', 'path' => '/media/social', 'icon' => 'share', 'module' => ['sales', 1]],
            ['key' => 'website', 'path' => '/media/website', 'icon' => 'globe', 'module' => ['sales', 1]],
            ['key' => 'communication', 'path' => '/media/communication', 'icon' => 'megaphone', 'module' => ['sales', 1]],
        ],
        'finance' => [
            ['key' => 'preinvoice', 'path' => '/pre-invoicing', 'icon' => 'invoice', 'module' => ['preinvoice', 1]],
        ],
        'settings' => [
            ['key' => 'users', 'path' => '/settings/users', 'icon' => 'user', 'right' => 'users.manage', 'also' => ['/settings/roles', '/settings/activity']],
            ['key' => 'company', 'path' => '/settings/company', 'icon' => 'building', 'module' => ['settings', 2]],
            ['key' => 'documents', 'path' => '/settings/documents', 'icon' => 'doc', 'module' => ['settings', 2]],
            ['key' => 'vat', 'path' => '/settings/vat-rates', 'icon' => 'percent', 'module' => ['settings', 2]],
            ['key' => 'locations', 'path' => '/settings/warehouse-locations', 'icon' => 'pin', 'right' => 'warehouse.setup_locations'],
            ['key' => 'import', 'path' => '/settings/import', 'icon' => 'upload', 'module' => ['settings', 2]],
        ],
    ],

    'account' => [
        ['key' => 'preferences', 'path' => '/me/preferences', 'icon' => 'sliders'],
        ['key' => 'profile', 'path' => '/me/profile', 'icon' => 'user'],
        ['key' => 'notifications', 'path' => '/me/notifications', 'icon' => 'bell'],
        ['key' => 'help', 'path' => '/help', 'icon' => 'help'],
    ],
];
