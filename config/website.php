<?php

/*
|--------------------------------------------------------------------------
| Public website (cluk.live)
|--------------------------------------------------------------------------
| The website runs inside this app. Until cluk.live points to the app, the
| pages are previewed under app.cluk.live/site. Set WEBSITE_HOST=www.cluk.live
| (and point cluk.live and www.cluk.live to the same public folder) to serve them
| there; cluk.live and the old app.cluk.live/site links then forward to it.
*/

return [
    'host' => env('WEBSITE_HOST'),

    // The catalogue shows what is in the platform: every active dry-hire item and
    // package that has a place in the categories. Set to true to show only the
    // items ticked "Show on the website".
    'only_ticked' => (bool) env('WEBSITE_ONLY_TICKED', false),

    'dryhire_kinds' => ['item', 'package'],

    // Photo sizes the website asks for (width in pixels).
    'photo_widths' => [480, 1200],

    // Most requested words, shown under the search bar.
    'popular' => ['L-Acoustics', 'KS28', 'Yamaha CL5', 'moving head', 'wireless', 'truss'],

    'social' => [
        'instagram' => env('SOCIAL_INSTAGRAM', 'https://www.instagram.com/cluk_group/'),
        'facebook' => env('SOCIAL_FACEBOOK', 'https://www.facebook.com/profile.php?id=61578610427811'),
        'linkedin' => env('SOCIAL_LINKEDIN'), // no CLUK page known yet: the icon stays hidden until a link is set
    ],

    // Downloads page (footer): the groups, in this order. Terms and conditions are always shown (rental.terms_files).
    'download_categories' => ['terms', 'pricelists', 'technical', 'forms', 'brochures', 'other'],

    // A request sent from the website: max lines and quantity per line.
    'max_lines' => 200,
    'max_qty' => 9999,
];
