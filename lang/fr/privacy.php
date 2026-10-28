<?php

// Website privacy policy (GDPR). :company, :address, :vat and :email come from Settings › Company details.
return [
    'title' => 'Politique de confidentialité',
    'meta' => 'Comment CLUK traite vos données personnelles sur cluk.live, dans My CLUK et dans nos e-mails.',
    'updated' => 'Dernière mise à jour : :date',
    'intro' => 'Cette politique explique quelles données personnelles :company collecte via ce site, votre compte My CLUK et nos e-mails, pourquoi, combien de temps nous les gardons et quels sont vos droits.',
    'sections' => [
        [
            'h' => 'Qui nous sommes',
            'p' => [':company, :address, TVA :vat, est responsable de vos données personnelles (le « responsable du traitement »). Pour toute question sur la vie privée, écrivez à :email.'],
        ],
        [
            'h' => 'Ce que nous collectons et pourquoi',
            'li' => [
                'Demandes et contact : lorsque vous envoyez une demande de location (dry-hire) ou d’achat, réservez Cluk Factory ou nous écrivez, nous gardons votre nom, société, adresse e-mail, téléphone, numéro de TVA et votre message, pour vous répondre et faire une offre (mesures précontractuelles).',
                'Compte My CLUK : votre nom, adresse e-mail, téléphone, société et numéro de TVA, ainsi que les offres, commandes, factures et paniers de votre société, pour gérer votre compte et nos accords (contrat). Votre mot de passe est stocké chiffré ; notre personnel ne peut pas le voir.',
                'Téléchargements : pour certains documents, nous demandons d’abord votre nom et votre adresse e-mail, pour savoir qui les a reçus et donner suite à votre question (intérêt légitime).',
                'Statistiques du site, uniquement avec votre consentement : les pages et produits que vous consultez, ce que vous cherchez et mettez dans un panier, liés à un numéro de visiteur aléatoire dans un cookie. Nous ne gardons pas votre adresse IP. Dès que vous envoyez une demande, vous connectez ou téléchargez avec votre e-mail, ces visites vous sont liées, pour que notre équipe puisse mieux vous aider. Vous pouvez retirer votre consentement à tout moment via « Paramètres des cookies ».',
                'Discussion avec Clukie : sur les pages de vente, vous pouvez discuter avec Clukie, notre assistant IA. Nous gardons ce que vous écrivez, les réponses, la page où vous étiez et, si vous les donnez, votre nom, votre adresse e-mail et votre téléphone, pour répondre à votre question et, si vous le demandez, la transmettre à notre équipe (mesures précontractuelles et intérêt légitime). Ne partagez pas de données personnelles sensibles dans la discussion.',
                'E-mails : newsletters (seulement si vous avez dit oui), promotions et invitations aux clients existants, chacune avec un lien de désinscription (intérêt légitime ; vous pouvez vous y opposer à tout moment). Si un e-mail ne peut pas vous être remis, nous arrêtons d’écrire à cette adresse et la vérifions avec vous.',
                'Membres d’Afrit 6 et réservations Cluk Factory : les données que vous donnez à l’inscription ou à la réservation, ainsi que vos réservations, bureaux et demandes (contrat).',
                'Données d’entreprise : nous vérifions les numéros de TVA auprès du service européen VIES et les données d’entreprise auprès de la Banque-Carrefour des Entreprises (BCE) et de la Banque nationale, pour facturer correctement (obligation légale et intérêt légitime).',
            ],
        ],
        [
            'h' => 'Cookies',
            'p' => ['Les cookies nécessaires font fonctionner le site : votre session et connexion, la sécurité contre les formulaires falsifiés, votre langue, votre panier et votre choix de cookies. Ils ne demandent pas de consentement. Les cookies de statistiques (cluk_consent et cluk_vid) ne sont placés que si vous cliquez sur « Accepter les statistiques ». Nous n’utilisons pas de cookies publicitaires ni de traceurs de tiers.'],
        ],
        [
            'h' => 'Avec qui nous partageons',
            'p' => ['Nous ne vendons jamais vos données. Nous ne partageons que le nécessaire avec les sociétés qui nous aident : one.com (hébergement, dans l’UE), Microsoft 365 (e-mail, centres de données dans l’UE), OkiOki (notre comptabilité et facturation électronique, Belgique) et le réseau Peppol pour les factures électroniques. Nos actualités viennent de nos propres pages Facebook, Instagram et LinkedIn ; les images sont affichées depuis notre propre serveur, et si vous cliquez, la politique de cette plateforme s’applique. Ce que vous écrivez à Clukie est envoyé à Anthropic (la société derrière le modèle d’IA Claude, établie aux États-Unis), uniquement pour rédiger la réponse. Anthropic le traite pour notre compte, dans le cadre d’un accord de sous-traitance avec les clauses contractuelles types de l’UE, et ne l’utilise pas pour entraîner ses modèles.'],
        ],
        [
            'h' => 'Combien de temps nous gardons',
            'li' => [
                'Statistiques du site : 13 mois, puis supprimées automatiquement.',
                'Discussions avec Clukie : 12 mois après le dernier message, puis supprimées automatiquement. Lorsqu’une discussion est transmise à notre équipe, elle est aussi conservée comme demande (voir ci-dessus).',
                'Demandes sans commande : jusqu’à 3 ans après le dernier contact.',
                'Comptes, offres, commandes et factures : tant que votre compte ou notre relation existe, puis aussi longtemps que la législation comptable et fiscale l’exige.',
                'Listes d’envoi : jusqu’à votre désinscription.',
            ],
        ],
        [
            'h' => 'Vos droits',
            'p' => ['Vous pouvez demander à consulter, corriger ou supprimer vos données, à en limiter l’usage, à les recevoir dans un format courant, et vous opposer à leur usage pour les e-mails ou les statistiques. Écrivez à :email ; nous répondons dans un délai d’un mois. Si vous n’êtes pas satisfait, vous pouvez porter plainte auprès de l’Autorité de protection des données belge (www.autoriteprotectiondonnees.be).'],
        ],
        [
            'h' => 'Sécurité',
            'p' => ['Notre site ne fonctionne que via une connexion chiffrée. Le personnel se connecte avec Microsoft 365 et ne voit que ce dont son rôle a besoin ; les modifications importantes sont enregistrées. Les mots de passe des clients et des membres sont stockés chiffrés.'],
        ],
        [
            'h' => 'Modifications',
            'p' => ['Lorsque cette politique change, nous publions ici la nouvelle version avec sa date.'],
        ],
    ],
    'link' => 'Politique de confidentialité',
    'agree' => 'Comment nous traitons vos données : voir notre :link.',
];
