<?php

// Website privacy policy (GDPR). :company, :address, :vat and :email come from Settings › Company details.
return [
    'title' => 'Política de privacidad',
    'meta' => 'Cómo trata CLUK tus datos personales en cluk.live, en My CLUK y en nuestros correos.',
    'updated' => 'Última actualización: :date',
    'intro' => 'Esta política explica qué datos personales recoge :company a través de esta web, tu cuenta My CLUK y nuestros correos, por qué lo hacemos, cuánto tiempo los guardamos y cuáles son tus derechos.',
    'sections' => [
        [
            'h' => 'Quiénes somos',
            'p' => [':company, :address, IVA :vat, es responsable de tus datos personales (el «responsable del tratamiento»). Para cualquier pregunta sobre privacidad, escribe a :email.'],
        ],
        [
            'h' => 'Qué recogemos y por qué',
            'li' => [
                'Solicitudes y contacto: cuando envías una solicitud de alquiler (dry-hire) o de compra, reservas Cluk Factory o nos escribes, guardamos tu nombre, empresa, correo, teléfono, número de IVA y tu mensaje, para responderte y hacer una oferta (medidas precontractuales).',
                'Cuenta My CLUK: tu nombre, correo, teléfono, empresa y número de IVA, y los presupuestos, pedidos, facturas y cestas de tu empresa, para gestionar tu cuenta y nuestros acuerdos (contrato). Tu contraseña se guarda cifrada; nuestro personal no puede verla.',
                'Descargas: para algunos documentos pedimos antes tu nombre y correo, para saber quién los recibió y dar seguimiento a tu consulta (interés legítimo).',
                'Estadísticas de la web, solo con tu consentimiento: qué páginas y productos miras, qué buscas y añades a una cesta, vinculado a un número de visitante aleatorio en una cookie. No guardamos tu dirección IP. En cuanto envías una solicitud, inicias sesión o descargas con tu correo, estas visitas se vinculan contigo, para que nuestro equipo pueda ayudarte mejor. Puedes retirar tu consentimiento en cualquier momento en «Configuración de cookies».',
                'Correos: boletines (solo si dijiste que sí), promociones e invitaciones a clientes existentes, cada uno con un enlace para darte de baja (interés legítimo; puedes oponerte en cualquier momento). Si un correo no se te puede entregar, dejamos de escribir a esa dirección y la comprobamos contigo.',
                'Miembros de Afrit 6 y reservas de Cluk Factory: los datos que das al registrarte o reservar, y tus reservas, puestos y solicitudes (contrato).',
                'Datos de empresa: comprobamos los números de IVA en el servicio europeo VIES y los datos de empresa en el registro mercantil belga (KBO/BCE) y el Banco Nacional, para facturar correctamente (obligación legal e interés legítimo).',
            ],
        ],
        [
            'h' => 'Cookies',
            'p' => ['Las cookies necesarias hacen funcionar la web: tu sesión e inicio de sesión, la seguridad frente a formularios falsificados, tu idioma, tu cesta y tu elección de cookies. No requieren consentimiento. Las cookies de estadísticas (cluk_consent y cluk_vid) solo se colocan si haces clic en «Aceptar estadísticas». No usamos cookies publicitarias ni rastreadores de terceros.'],
        ],
        [
            'h' => 'Con quién compartimos',
            'p' => ['Nunca vendemos tus datos. Solo compartimos lo necesario con las empresas que nos ayudan: one.com (alojamiento, en la UE), Microsoft 365 (correo, centros de datos en la UE), OkiOki (nuestra contabilidad y facturación electrónica, Bélgica) y la red Peppol para facturas electrónicas. Nuestras noticias vienen de nuestras propias páginas de Facebook, Instagram y LinkedIn; las imágenes se muestran desde nuestro propio servidor, y si haces clic, se aplica la política de esa plataforma.'],
        ],
        [
            'h' => 'Cuánto tiempo guardamos',
            'li' => [
                'Estadísticas de la web: 13 meses, después se borran automáticamente.',
                'Solicitudes sin pedido: hasta 3 años después del último contacto.',
                'Cuentas, presupuestos, pedidos y facturas: mientras dure tu cuenta o nuestra relación, y después el tiempo que exija la legislación contable y fiscal.',
                'Listas de correo: hasta que te des de baja.',
            ],
        ],
        [
            'h' => 'Tus derechos',
            'p' => ['Puedes pedir ver, corregir o borrar tus datos, limitar su uso, recibirlos en un formato común y oponerte a su uso para correos o estadísticas. Escribe a :email; respondemos en el plazo de un mes. Si no estás satisfecho, puedes presentar una reclamación ante la Autoridad de Protección de Datos de Bélgica (www.dataprotectionauthority.be).'],
        ],
        [
            'h' => 'Seguridad',
            'p' => ['Nuestra web solo funciona con una conexión cifrada. El personal inicia sesión con Microsoft 365 y solo ve lo que su función necesita; los cambios importantes quedan registrados. Las contraseñas de clientes y miembros se guardan cifradas.'],
        ],
        [
            'h' => 'Cambios',
            'p' => ['Cuando esta política cambie, publicaremos aquí la nueva versión con su fecha.'],
        ],
    ],
    'link' => 'Política de privacidad',
    'agree' => 'Cómo tratamos tus datos: consulta nuestra :link.',
];
