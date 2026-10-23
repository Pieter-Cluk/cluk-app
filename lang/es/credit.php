<?php

// Customer card › Credit check (KBO/BCE register, VIES, our own payments).
return [
    'title' => 'Control de crédito',
    'levels' => [
        'green' => 'Sin señales de alerta',
        'orange' => 'Precaución',
        'red' => 'Se aconseja pago por adelantado',
        'unknown' => 'No hay suficiente información',
    ],
    'advice' => [
        'green' => 'plazo de pago normal',
        'orange' => 'comprueba antes de dar crédito o aceptar un pedido grande',
        'red' => 'pide pago por adelantado o un anticipo',
        'unknown' => 'aún no hay datos del registro ni historial de pagos',
    ],
    'no_signs' => 'El registro, el número de IVA y la forma en que nos pagan no muestran nada inusual.',
    'reasons' => [
        'not_found' => 'El número de empresa :number no está en el registro (KBO).',
        'situation' => 'Registro (KBO): :what.',
        'situation_other' => 'El registro (KBO) muestra una situación especial: :what.',
        'stopped' => 'Registro (KBO): la empresa está dada de baja.',
        'young' => 'Empresa joven: empezó el :date.',
        'vies_invalid' => 'El número de IVA no es válido (VIES).',
        'overdue' => ':n factura(s) vencida(s), hasta :days días.',
        'paid_late' => ':n de las últimas :of facturas pagadas con retraso, de media :days días.',
    ],
    'reg' => [
        'name' => 'Nombre oficial',
        'form' => 'Forma jurídica',
        'status' => 'Estado',
        'active' => 'Activa',
        'stopped' => 'De baja',
        'since' => 'Desde',
        'years' => '{0} menos de un año|{1} 1 año|[2,*] :n años',
        'address' => 'Domicilio social',
        'source' => 'Registro (KBO) del :date',
        'open' => 'Abrir en el KBO',
        'pending' => 'Los datos del registro llegan con la próxima actualización del KBO.',
        'not_belgian' => 'Datos del registro solo para empresas belgas; VIES y nuestro historial de pagos sí cuentan.',
    ],
    'nbb_later' => 'Las cuentas anuales y el gráfico de crecimiento llegarán cuando la conexión con el Banco Nacional esté activa.',
    'mail' => [
        'subject' => 'Alerta de crédito: :name',
        'intro' => 'El control de crédito de :name se ha puesto en rojo.',
        'reason' => 'Motivo',
        'next' => 'Pide pago por adelantado o un anticipo en los pedidos nuevos hasta que esté claro.',
        'button' => 'Abrir la ficha del cliente',
    ],
];
