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
        'afrit6.approve_members' => 'Approve new Afrit 6 members',
        'afrit6.door_codes' => 'See and change door codes',
        'companies.see_health' => 'See financial health and credit',
        'finance.see_margins' => 'See purchase prices and margins',
        'finance.send_to_okioki' => 'Approve proformas and send to OkiOki',
        'gdpr.export_erase' => 'GDPR export and erase',
        'orders.approve' => 'Orders: approve and send the order confirmation',
        'sales.approve_low_margin' => 'Sales: approve quotes under 10% margin',
        'sales.edit_items' => 'Sales items: add and edit',
        'users.manage' => 'Manage users and roles',
        'warehouse.release_repair' => 'Warehouse: release items from repair',
        'warehouse.setup_locations' => 'Warehouse: set up locations and print labels',
        'warehouse.sign_delivery' => 'Warehouse: sign delivery notes and hand over',
        'warehouse.write_off' => 'Warehouse: write off lost or broken stock',
    ],
    'app' => [
        'name' => 'CLUK',
        'tagline' => 'Connecting the dots',
    ],
];
