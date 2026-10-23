<?php

// Customer card › Credit check (KBO/BCE register, VIES, our own payments).
return [
    'title' => 'Kredietcheck',
    'levels' => [
        'green' => 'Geen waarschuwingen',
        'orange' => 'Opgelet',
        'red' => 'Vooruitbetaling aangeraden',
        'unknown' => 'Te weinig informatie',
    ],
    'advice' => [
        'green' => 'normale betalingstermijn',
        'orange' => 'controleer voor je krediet geeft of een grote bestelling aanneemt',
        'red' => 'vraag vooruitbetaling of een voorschot',
        'unknown' => 'nog geen registergegevens of betalingshistoriek',
    ],
    'no_signs' => 'Het register, het btw-nummer en hoe ze ons betalen tonen niets ongewoons.',
    'reasons' => [
        'not_found' => 'Ondernemingsnummer :number staat niet in de KBO.',
        'situation' => 'KBO: :what.',
        'situation_other' => 'KBO toont een bijzondere toestand: :what.',
        'stopped' => 'KBO: de onderneming is stopgezet.',
        'young' => 'Jonge onderneming: gestart op :date.',
        'vies_invalid' => 'Btw-nummer is niet geldig (VIES).',
        'overdue' => ':n factuur/facturen vervallen, tot :days dagen.',
        'paid_late' => ':n van de laatste :of facturen te laat betaald, gemiddeld :days dagen.',
    ],
    'reg' => [
        'name' => 'Officiële naam',
        'form' => 'Rechtsvorm',
        'status' => 'Status',
        'active' => 'Actief',
        'stopped' => 'Stopgezet',
        'since' => 'Sinds',
        'years' => '{0} minder dan een jaar|{1} 1 jaar|[2,*] :n jaar',
        'address' => 'Maatschappelijke zetel',
        'source' => 'KBO van :date',
        'open' => 'Openen in de KBO',
        'pending' => 'De registergegevens komen met de volgende KBO-update.',
        'not_belgian' => 'Registergegevens enkel voor Belgische bedrijven; VIES en onze eigen betalingshistoriek tellen wel mee.',
    ],
    'nbb_later' => 'Jaarrekeningen en de groeigrafiek volgen zodra de koppeling met de Nationale Bank actief is.',
    'mail' => [
        'subject' => 'Kredietwaarschuwing: :name',
        'intro' => 'De kredietcheck van :name is rood geworden.',
        'reason' => 'Reden',
        'next' => 'Vraag vooruitbetaling of een voorschot op nieuwe bestellingen tot dit duidelijk is.',
        'button' => 'Open de klantenkaart',
    ],
];
