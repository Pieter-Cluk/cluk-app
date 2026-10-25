<?php

return [
    'title' => 'Commerciële taken',
    'intro' => 'Gemaakt door de app en vanzelf afgesloten als de reden weg is: klantenfiches die aandacht nodig hebben, offertes die te lang openstaan, klanten die actief zijn op de website of via onze sociale media komen.',
    'who' => [
        'me' => 'Van mij',
        'all' => 'Iedereen',
        'nobody' => 'Zonder eigenaar',
    ],
    'all_levels' => 'Alle kleuren',
    'all_kinds' => 'Alle soorten',
    'levels' => [
        'red' => 'Nu doen',
        'orange' => 'Belangrijk',
        'yellow' => 'Aandacht',
        'blue' => 'Kans',
    ],
    'kinds' => [
        'card' => 'Klantenfiche',
        'quote' => 'Open offerte',
        'web' => 'Website & sociale media',
    ],
    'card' => [
        'title' => '— de fiche heeft aandacht nodig',
    ],
    'problems' => [
        'no_email' => 'geen e-mail',
        'no_phone' => 'geen telefoon',
        'no_address' => 'adres onvolledig',
        'no_vat' => 'geen btw-nummer',
        'vat_invalid' => 'btw-nummer ongeldig (VIES)',
        'no_owner' => 'geen accountverantwoordelijke',
        'no_contact' => 'geen contactpersoon',
        'no_contact_email' => 'contactpersonen zonder e-mail',
        'credit_red' => 'kredietcheck rood',
    ],
    'quote' => [
        'open' => '{0} vandaag verstuurd|{1} 1 dag open|[2,*] :n dagen open',
        'expired' => 'geldigheid verlopen',
    ],
    'web' => [
        'downloaded' => 'downloadde :docs',
        'title' => '— actief op de website',
        'views' => '{1} 1 pagina|[2,*] :n pagina’s',
        'products' => '{1} 1 product bekeken|[2,*] :n producten bekeken',
        'basket' => 'iets in het mandje gelegd',
        'searched' => 'zocht naar “:words”',
        'via' => 'kwam via :where',
    ],
    'call' => 'contact: :name',
    'no_owner' => 'geen eigenaar',
    'since' => 'sinds :when',
    'done' => 'Gedaan',
    'snooze' => 'Later',
    'days' => '{1} morgen|[2,*] over :n dagen',
    'empty' => 'Niets te doen hier. Goed bezig!',
    'legend' => 'Rood: nu doen (foute gegevens, kredietcheck rood, offerte 20+ dagen of verlopen) · Oranje: belangrijk (10+ dagen; factuurgegevens ontbreken terwijl er werk loopt; iets in het mandje op de website) · Geel: aandacht (5+ dagen; gegevens ontbreken) · Blauw: kans (actief op de website of via sociale media). “Gedaan” verbergt een taak tot de reden erger wordt.',
    'flash' => [
        'done' => 'Als gedaan gemarkeerd.',
        'snoozed' => '{1} Morgen terug.|[2,*] Over :n dagen terug.',
    ],
];
