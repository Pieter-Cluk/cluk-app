<?php

/*
|--------------------------------------------------------------------------
| Cluk Factory: the 720 m² event venue
|--------------------------------------------------------------------------
| Sizes and levels are priced by their service items (CF-…), so prices
| live in Dry-hire › Services. These are the rules of the booking page.
*/

return [
    'sizes' => [
        // code => [event floor m², entrance m², item number]
        'XS' => [120, 54, 'CF-XS'],
        'S' => [180, 81, 'CF-S'],
        'M' => [240, 108, 'CF-M'],
        'L' => [300, 135, 'CF-L'],
        'XL' => [360, 162, 'CF-XL'],
        'XXL' => [600, 54, 'CF-XXL'],
    ],

    // basic is included in the space price
    'levels' => [
        'basic' => null,
        'advanced' => 'CF-ADVANCED',
        'allin' => 'CF-ALLIN',
    ],

    'event_types' => ['launch', 'corporate', 'live', 'dj', 'livestream', 'fashion', 'activation', 'wedding', 'other'],

    'extras' => ['bus', 'stream', 'catering', 'security', 'dj', 'runway'],

    'max_days' => 3,
    'max_buildup' => 2,
    'max_breakdown' => 1,
    'max_guests' => 1200,

    // A held date stays held this many days (until 17:00), then it is free again.
    'option_days' => 3,

    // How far ahead the calendar on the booking page goes.
    'months_ahead' => 12,
];
