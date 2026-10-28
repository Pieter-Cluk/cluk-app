<?php

return [
    'title' => 'Tareas comerciales',
    'intro' => 'Creadas por la app y cerradas solas cuando desaparece el motivo: fichas de clientes que necesitan atención, presupuestos abiertos demasiado tiempo, clientes activos en la web o que llegan por nuestras redes sociales.',
    'who' => [
        'me' => 'Mías',
        'all' => 'Todos',
        'nobody' => 'Sin responsable',
    ],
    'all_levels' => 'Todos los colores',
    'all_kinds' => 'Todos los tipos',
    'levels' => [
        'red' => 'Ahora',
        'orange' => 'Importante',
        'yellow' => 'Atención',
        'blue' => 'Oportunidad',
    ],
    'kinds' => [
        'card' => 'Ficha de cliente',
        'quote' => 'Presupuesto abierto',
        'web' => 'Web y redes',
        'mail' => 'Correo devuelto',
    ],
    'card' => [
        'title' => '— la ficha necesita atención',
    ],
    'problems' => [
        'no_email' => 'sin e-mail',
        'no_phone' => 'sin teléfono',
        'no_address' => 'dirección incompleta',
        'no_vat' => 'sin NIF-IVA',
        'vat_invalid' => 'NIF-IVA no válido (VIES)',
        'no_owner' => 'sin responsable',
        'no_contact' => 'sin contacto',
        'no_contact_email' => 'contactos sin e-mail',
        'credit_red' => 'control de crédito rojo',
    ],
    'quote' => [
        'open' => '{0} enviado hoy|{1} abierto 1 día|[2,*] abierto :n días',
        'expired' => 'validez caducada',
    ],
    'web' => [
        'downloaded' => 'descargó :docs',
        'title' => '— activo en la web',
        'views' => '{1} 1 página|[2,*] :n páginas',
        'products' => '{1} 1 producto visto|[2,*] :n productos vistos',
        'basket' => 'puso algo en la cesta',
        'searched' => 'buscó “:words”',
        'via' => 'llegó vía :where',
    ],
    'call' => 'contacto: :name',
    'no_owner' => 'sin responsable',
    'since' => 'desde el :when',
    'done' => 'Hecho',
    'snooze' => 'Más tarde',
    'days' => '{1} mañana|[2,*] en :n días',
    'empty' => 'Nada que hacer aquí. ¡Bien hecho!',
    'legend' => 'Rojo: ahora (datos erróneos, crédito rojo, presupuesto de 20+ días o caducado) · Naranja: importante (10+ días; faltan datos de factura con trabajo en curso; algo en la cesta web) · Amarillo: atención (5+ días; faltan datos) · Azul: oportunidad (activo en la web o vía redes). «Hecho» oculta una tarea hasta que el motivo empeora.',
    'flash' => [
        'done' => 'Marcada como hecha.',
        'snoozed' => '{1} Vuelve mañana.|[2,*] Vuelve en :n días.',
    ],
    'mail' => [
        'title' => '— no se pudo entregar un correo',
        'todo' => 'Comprueba la dirección con el cliente y cámbiala en la ficha (o desbloquéala).',
    ],
];
