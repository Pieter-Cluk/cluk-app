<?php

// Website privacy policy (GDPR). :company, :address, :vat and :email come from Settings › Company details.
return [
    'title' => 'Privacybeleid',
    'meta' => 'Hoe CLUK omgaat met je persoonsgegevens op cluk.live, in My CLUK en in onze mails.',
    'updated' => 'Laatst bijgewerkt: :date',
    'intro' => 'Dit beleid legt uit welke persoonsgegevens :company verzamelt via deze website, je My CLUK-account en onze mails, waarom we dat doen, hoe lang we ze bewaren en wat je rechten zijn.',
    'sections' => [
        [
            'h' => 'Wie we zijn',
            'p' => [':company, :address, btw :vat, is verantwoordelijk voor je persoonsgegevens (de “verwerkingsverantwoordelijke”). Voor elke vraag over privacy mail je naar :email.'],
        ],
        [
            'h' => 'Wat we verzamelen en waarom',
            'li' => [
                'Aanvragen en contact: als je een dry-hire- of verkoopaanvraag stuurt, Cluk Factory boekt of ons mailt, bewaren we je naam, bedrijf, e-mailadres, telefoonnummer, btw-nummer en je bericht, om je te antwoorden en een offerte te maken (stappen vóór een overeenkomst).',
                'My CLUK-account: je naam, e-mailadres, telefoonnummer, bedrijf en btw-nummer, en de offertes, orders, facturen en mandjes van je bedrijf, om je account en onze afspraken te beheren (overeenkomst). Je wachtwoord wordt versleuteld bewaard; onze medewerkers kunnen het niet zien.',
                'Downloads: voor sommige documenten vragen we eerst je naam en e-mailadres, zodat we weten wie ze kreeg en je vraag kunnen opvolgen (gerechtvaardigd belang).',
                'Websitestatistieken, alleen met je toestemming: welke pagina’s en producten je bekijkt, wat je zoekt en in een mandje legt, gekoppeld aan een willekeurig bezoekersnummer in een cookie. We bewaren je IP-adres niet. Zodra je een aanvraag stuurt, inlogt of downloadt met je e-mailadres, worden deze bezoeken aan jou gekoppeld, zodat ons team je beter kan helpen. Je kan je toestemming altijd intrekken via “Cookie-instellingen”.',
                'Chat met Clukie: op de verkooppagina’s kan je chatten met Clukie, onze AI-helper. We bewaren wat je schrijft, de antwoorden, de pagina waarop je was en, als je ze geeft, je naam, e-mailadres en telefoonnummer, om je vraag te beantwoorden en, als je dat vraagt, door te geven aan ons team (stappen vóór een overeenkomst en gerechtvaardigd belang). Deel geen gevoelige persoonsgegevens in de chat.',
                'Mails: nieuwsbrieven (alleen als je ja zei), promoties en uitnodigingen aan bestaande klanten, telkens met een afmeldlink (gerechtvaardigd belang; je kan altijd bezwaar maken). Als een mail aan jou niet bezorgd kan worden, stoppen we met mailen naar dat adres en controleren we het met jou.',
                'Afrit 6-leden en Cluk Factory-boekingen: de gegevens die je opgeeft bij registratie of boeking, en je boekingen, bureaus en aanvragen (overeenkomst).',
                'Bedrijfsgegevens: voor bedrijven controleren we btw-nummers bij de Europese VIES-dienst en bedrijfsgegevens bij de Kruispuntbank van Ondernemingen (KBO) en de Nationale Bank, om correct te factureren (wettelijke verplichting en gerechtvaardigd belang).',
            ],
        ],
        [
            'h' => 'Cookies',
            'p' => ['Noodzakelijke cookies laten de site werken: je sessie en aanmelding, beveiliging tegen vervalste formulieren, je taal, je mandje en je cookiekeuze. Daarvoor is geen toestemming nodig. De statistiekcookies (cluk_consent en cluk_vid) worden alleen gezet als je op “Statistieken aanvaarden” klikt. We gebruiken geen advertentiecookies of trackers van anderen.'],
        ],
        [
            'h' => 'Met wie we delen',
            'p' => ['We verkopen je gegevens nooit. We delen alleen wat nodig is met de bedrijven die ons helpen: one.com (hosting, in de EU), Microsoft 365 (e-mail, datacenters in de EU), OkiOki (onze boekhouding en e-facturatie, België) en het Peppol-netwerk voor e-facturen. Ons nieuws komt van onze eigen Facebook-, Instagram- en LinkedIn-pagina’s; foto’s worden getoond vanaf onze eigen server, en als je doorklikt, geldt het beleid van dat platform. Wat je aan Clukie schrijft, wordt naar Anthropic gestuurd (het bedrijf achter het AI-model Claude, gevestigd in de Verenigde Staten), alleen om het antwoord te schrijven. Anthropic verwerkt het in onze opdracht, onder een verwerkersovereenkomst met de standaardcontractbepalingen van de EU, en gebruikt het niet om zijn modellen te trainen.'],
        ],
        [
            'h' => 'Hoe lang we bewaren',
            'li' => [
                'Websitestatistieken: 13 maanden, daarna automatisch gewist.',
                'Chats met Clukie: 12 maanden na het laatste bericht, daarna automatisch gewist. Als een chat aan ons team wordt doorgegeven, wordt hij ook als aanvraag bewaard (zie hierboven).',
                'Aanvragen zonder order: tot 3 jaar na het laatste contact.',
                'Accounts, offertes, orders en facturen: zolang je account of onze relatie loopt, en daarna zolang de boekhoud- en fiscale wetgeving het vraagt.',
                'Mailinglijsten: tot je je afmeldt.',
            ],
        ],
        [
            'h' => 'Je rechten',
            'p' => ['Je kan vragen om je gegevens in te zien, te verbeteren of te wissen, het gebruik te beperken, ze in een gangbaar formaat te krijgen, en bezwaar maken tegen het gebruik voor mails of statistieken. Mail naar :email; we antwoorden binnen een maand. Ben je niet tevreden, dan kan je klacht indienen bij de Belgische Gegevensbeschermingsautoriteit (www.gegevensbeschermingsautoriteit.be).'],
        ],
        [
            'h' => 'Beveiliging',
            'p' => ['Onze site werkt alleen via een versleutelde verbinding. Medewerkers melden zich aan met Microsoft 365 en zien alleen wat hun rol nodig heeft; belangrijke wijzigingen worden bijgehouden. Wachtwoorden van klanten en leden worden versleuteld bewaard.'],
        ],
        [
            'h' => 'Wijzigingen',
            'p' => ['Als dit beleid verandert, publiceren we de nieuwe versie hier met de datum.'],
        ],
    ],
    'link' => 'Privacybeleid',
    'agree' => 'Hoe we met je gegevens omgaan: zie ons :link.',
];
