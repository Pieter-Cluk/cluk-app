<?php

// Afrit 6 member help: titles, intros and the steps of each video (same order as config/afrit6-help.php).
return [
    'title' => 'Ayuda',
    'intro' => 'Vídeos cortos que muestran cómo hacerlo todo en tu portal de Afrit 6. Elige lo que quieres hacer.',
    'steps_title' => 'Paso a paso',
    'watch' => 'Ver el vídeo',
    'back' => 'Toda la ayuda',
    'video_note' => 'Los vídeos están en inglés; los pasos de abajo están en español.',
    'ask' => '¿No lo encuentras? Pregunta a Clukie, abajo a la derecha.',
    'outro' => '',
    'topics' => [
        'tour' => [
            'title' => 'Tu portal en un minuto',
            'intro' => 'Qué encuentras dónde en tu portal de Afrit 6.',
            'steps' => [
                'Bienvenido a tu portal de Afrit 6. Aquí lo organizas todo para tu puesto de trabajo.',
                'Arriba están las pestañas: Puestos, Sala de reuniones, Invitados, Servicios de almacén y Mi factura.',
                'Tu código de puerta siempre está en el resumen. Haz clic en Mostrar para verlo.',
                'Aquí ves tus propios puestos.',
                'Y tus próximas reservas de la sala, con tus momentos gratis de esta semana.',
                'Abajo ves lo que ya has usado este mes.',
                '¿Preguntas? En Ayuda encuentras todos los vídeos, y Clukie te ayuda abajo a la derecha.',
            ],
        ],
        'desk_day' => [
            'title' => 'Un puesto para medio día o un día',
            'intro' => 'Cómo reservar un puesto libre para una mañana, una tarde o un día entero.',
            'steps' => [
                'Ve a la pestaña Puestos.',
                'Arriba reservas un puesto para una mañana, una tarde o un día entero.',
                'Elige la fecha y la parte del día.',
                'Haz clic en Ver puestos libres.',
                'Haz clic en un puesto libre. Queda reservado al instante.',
                'Tu reserva aparece debajo. Mientras no haya empezado, aún puedes cancelarla.',
            ],
        ],
        'fixed_desk' => [
            'title' => 'Tu puesto fijo: cambiar o añadir uno',
            'intro' => 'Cómo cambiar de puesto o pedir un puesto fijo adicional.',
            'steps' => [
                'En Puestos fijos por mes ves todos los puestos.',
                'El verde es tu puesto. El gris está alquilado, una línea de puntos significa libre.',
                'Haz clic en un puesto libre.',
                '¿Quieres mudarte? Haz clic en Cambiar. Desde mañana te sientas allí, al mismo precio.',
                'O pídelo como puesto adicional: elige desde cuándo y envía la solicitud.',
                '¿Prefieres tu propia oficina? Pide un precio en Oficina privada.',
            ],
        ],
        'room' => [
            'title' => 'Reservar la sala de reuniones',
            'intro' => 'Cómo reservar la sala por media hora.',
            'steps' => [
                'Cada semana tienes por puesto un momento gratis de hasta 2 horas.',
                'Elige el día arriba.',
                'El plan del día muestra qué momentos están libres.',
                'Elige la disposición: reunión o clase.',
                'Elige la hora de inicio.',
                'Elige cuánto tiempo necesitas la sala.',
                'Haz clic en Reservar.',
                'Tu reserva aparece en Tus reservas. Allí también puedes cancelarla.',
            ],
        ],
        'guests' => [
            'title' => 'Anunciar a tus invitados',
            'intro' => 'Cómo avisarnos de quién viene de visita.',
            'steps' => [
                'Ve a la pestaña Invitados.',
                'Elige la fecha y la hora de la visita.',
                'Escribe el nombre de tu invitado. ¿Varios invitados? Un nombre por línea.',
                'Añade su empresa si quieres.',
                'Marca si el nombre puede salir en la pantalla de bienvenida de la entrada.',
                'Haz clic en Añadir.',
                'Tus invitados aparecen en Invitados previstos.',
            ],
        ],
        'storage' => [
            'title' => 'Almacenaje: una estantería o una plaza de palé',
            'intro' => 'Cómo pedir almacenaje en el almacén.',
            'steps' => [
                'Ve a Servicios de almacén. A la derecha empiezas una nueva solicitud.',
                'Elige Estanterías. Para cosas más grandes, elige Plazas de palé.',
                'Indica cuántas necesitas y desde cuándo.',
                'Añade una nota si quieres.',
                'Haz clic en Enviar solicitud. El equipo de Afrit 6 confirma tu solicitud.',
                'En Tus solicitudes lo sigues todo. Pagas por día usado y paras el alquiler aquí mismo.',
            ],
        ],
        'parcels' => [
            'title' => 'Recibir y enviar paquetes',
            'intro' => 'Cómo avisarnos de que llega o sale mercancía.',
            'steps' => [
                'En Servicios de almacén, abre Recepción de mercancía.',
                'Indica cuántos bultos o palés llegan, y cuándo.',
                'Elige: en la estantería de recogida de 24 horas, o directamente a tu almacenaje.',
                'Haz clic en Enviar solicitud. El equipo lo recibe todo por ti.',
                '¿Envías algo? Usa Envío de mercancía e indica el transportista.',
                'También podemos embalar por ti: elige Embalaje.',
            ],
        ],
        'door_bill' => [
            'title' => 'Tu código de puerta y tu factura',
            'intro' => 'Dónde encontrar tu código de puerta y tu factura en curso.',
            'steps' => [
                'En el resumen, haz clic en Mostrar junto a Código de puerta.',
                '¿Necesitas un código nuevo? Pídelo aquí.',
                'Ve a la pestaña Mi factura.',
                'Aquí ves tu factura en curso de este mes de contrato, sin IVA.',
                'Debajo están tus meses anteriores.',
            ],
        ],
    ],
];
