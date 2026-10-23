<?php

return [
    'modules' => [
        'companies' => 'Companies & contacts',
        'sales' => 'Quotes & sales',
        'purchasing' => 'Purchasing',
        'dryhire' => 'Dry-hire',
        'warehouse' => 'Warehouse',
        'factory' => 'Cluk Factory',
        'afrit6' => 'Cluk Workspace (Afrit 6)',
        'preinvoice' => 'Pre-invoicing',
        'settings' => 'Users & settings',
    ],
    'levels' => [
        'none' => 'No access',
        'view' => 'View',
        'edit' => 'Edit',
        'manage' => 'Manage',
    ],
    'rights' => [
        'afrit6' => [
            'approve_members' => 'Approve new Afrit 6 members',
            'door_codes' => 'See and change door codes',
        ],
        'companies' => [
            'see_health' => 'See financial health and credit',
        ],
        'finance' => [
            'see_margins' => 'See purchase prices and margins',
            'send_to_okioki' => 'Approve proformas and send to OkiOki',
            'vat_treatment' => 'Change the VAT treatment of a company',
        ],
        'equipment' => [
            'website' => 'Products: choose what shows on the website',
        ],
        'gdpr' => [
            'export_erase' => 'GDPR export and erase',
        ],
        'help' => [
            'manage' => 'Help: write articles and answer questions',
        ],
        'orders' => [
            'approve' => 'Orders: approve and send the order confirmation',
        ],
        'products' => [
            'classify' => 'Products: manage categories and attributes',
        ],
        'sales' => [
            'approve_low_margin' => 'Sales: approve quotes under 10% margin',
            'edit_items' => 'Sales items: add and edit',
        ],
        'users' => [
            'manage' => 'Manage users and roles',
        ],
        'warehouse' => [
            'release_repair' => 'Warehouse: release items from repair',
            'setup_locations' => 'Warehouse: set up locations and print labels',
            'sign_delivery' => 'Warehouse: sign delivery notes and hand over',
            'write_off' => 'Warehouse: write off lost or broken stock',
        ],
    ],
    'app' => [
        'name' => 'CLUK',
        'tagline' => 'Connecting the dots',
    ],
];
