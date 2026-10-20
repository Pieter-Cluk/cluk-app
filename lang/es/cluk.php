<?php

return [
    'modules' => [
        'companies' => 'Empresas y contactos',
        'sales' => 'Presupuestos y ventas',
        'purchasing' => 'Compras',
        'dryhire' => 'Alquiler en seco',
        'warehouse' => 'Almacén',
        'factory' => 'Cluk Factory',
        'afrit6' => 'Cluk Workspace (Afrit 6)',
        'preinvoice' => 'Prefacturación',
        'settings' => 'Usuarios y ajustes',
    ],
    'levels' => [
        'none' => 'Sin acceso',
        'view' => 'Ver',
        'edit' => 'Editar',
        'manage' => 'Gestionar',
    ],
    'rights' => [
        'afrit6' => [
            'approve_members' => 'Aprobar nuevos miembros de Afrit 6',
            'door_codes' => 'Ver y cambiar códigos de puerta',
        ],
        'companies' => [
            'see_health' => 'Ver salud financiera y crédito',
        ],
        'finance' => [
            'see_margins' => 'Ver precios de compra y márgenes',
            'send_to_okioki' => 'Aprobar proformas y enviarlas a OkiOki',
        ],
        'gdpr' => [
            'export_erase' => 'Exportación y borrado RGPD',
        ],
        'orders' => [
            'approve' => 'Pedidos: aprobar y enviar la confirmación',
        ],
        'sales' => [
            'approve_low_margin' => 'Ventas: aprobar presupuestos con menos del 10 % de margen',
            'edit_items' => 'Artículos de venta: añadir y editar',
        ],
        'users' => [
            'manage' => 'Gestionar usuarios y roles',
        ],
        'warehouse' => [
            'release_repair' => 'Almacén: liberar artículos de reparación',
            'setup_locations' => 'Almacén: configurar ubicaciones e imprimir etiquetas',
            'sign_delivery' => 'Almacén: firmar albaranes y entregar',
            'write_off' => 'Almacén: dar de baja stock perdido o roto',
        ],
    ],
    'app' => [
        'name' => 'CLUK',
        'tagline' => 'Connecting the dots',
    ],
];
