<?php

return [
    'title' => 'Commercial tasks',
    'intro' => 'Made by the app and closed by itself when the reason is gone: customer cards that need attention, quotes open too long, customers active on the website or coming via our social media.',
    'who' => [
        'me' => 'Mine',
        'all' => 'Everyone',
        'nobody' => 'Without owner',
    ],
    'all_levels' => 'All colours',
    'all_kinds' => 'All kinds',
    'levels' => [
        'red' => 'Act now',
        'orange' => 'Important',
        'yellow' => 'Attention',
        'blue' => 'Opportunity',
    ],
    'kinds' => [
        'card' => 'Customer card',
        'quote' => 'Open quote',
        'web' => 'Website & social',
    ],
    'card' => [
        'title' => '— the card needs attention',
    ],
    'problems' => [
        'no_email' => 'no email',
        'no_phone' => 'no phone',
        'no_address' => 'address incomplete',
        'no_vat' => 'no VAT number',
        'vat_invalid' => 'VAT number not valid (VIES)',
        'no_owner' => 'no account owner',
        'no_contact' => 'no contact person',
        'no_contact_email' => 'contacts without email',
        'credit_red' => 'credit check red',
    ],
    'quote' => [
        'open' => '{0} sent today|{1} open for 1 day|[2,*] open for :n days',
        'expired' => 'validity expired',
    ],
    'web' => [
        'downloaded' => 'downloaded :docs',
        'title' => '— active on the website',
        'views' => '{1} 1 page|[2,*] :n pages',
        'products' => '{1} 1 product looked at|[2,*] :n products looked at',
        'basket' => 'put something in the basket',
        'searched' => 'searched for “:words”',
        'via' => 'came via :where',
    ],
    'call' => 'contact: :name',
    'no_owner' => 'no owner',
    'since' => 'since :when',
    'done' => 'Done',
    'snooze' => 'Later',
    'days' => '{1} tomorrow|[2,*] in :n days',
    'empty' => 'Nothing to do here. Well done!',
    'legend' => 'Red: act now (wrong data, red credit check, quote 20+ days or expired) · Orange: important (10+ days; missing invoice data while work is running; something in the website basket) · Yellow: attention (5+ days; missing data) · Blue: opportunity (active on the website or via social media). “Done” hides a task until the reason gets worse.',
    'flash' => [
        'done' => 'Marked as done.',
        'snoozed' => '{1} Back tomorrow.|[2,*] Back in :n days.',
    ],
];
