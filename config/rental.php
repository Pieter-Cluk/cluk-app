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

    // Link to CLUK's general rental conditions, shown on the accept page (null = no link yet).
    'terms_url' => env('RENTAL_TERMS_URL'),

];
