<?php

/*
|--------------------------------------------------------------------------
| Rental quotes
|--------------------------------------------------------------------------
| The factor turns a day price into the price for the whole rental. It
| follows the days the customer USES the equipment, not the days it is out
| of the warehouse. A salesperson can change it per line.
*/

return [

    // Days used => factor. Above the last step: + per_extra_week for every started week.
    'factor' => [
        'day' => 1.0,          // 1 day
        'week' => 1.5,         // 2 – 7 days
        'two_weeks' => 2.5,    // 8 – 14 days
        'three_weeks' => 3.0,  // 15 – 21 days
        'per_extra_week' => 0.5,
    ],

    // Price units that are multiplied by the factor; others (event, coli…) are charged once per qty.
    'factor_units' => ['day', 'half_day'],

    // CLUK's general terms and conditions (PDF in public/), served at /terms.
    // The customer ticks them when accepting a quote; emails and documents link to them.
    'terms_file' => 'docs/CLUK_BV_Terms_and_conditions_EN.pdf',
    'terms_url' => env('RENTAL_TERMS_URL', '/terms'),

];
