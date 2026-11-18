<?php

/*
|--------------------------------------------------------------------------
| Warehouse: the return route and the check on the test bench
|--------------------------------------------------------------------------
| Cleaning prices are sample amounts until CLUK sets its own (excl. VAT, cents).
| Nothing is charged to a customer before the office approves it.
*/

return [
    // Cleaning stage => extra cost for the customer. Stage 1 is a normal clean.
    // 1 clean (a dry cloth), 2 not clean (wet cloth and products), 3 dirty (in depth: products, air pressure, opened).
    'cleaning' => [1 => 0, 2 => 1000, 3 => 3000],

    // A photo is required from cleaning stage 2 (it is charged), and at the check when something is missing, broken or damaged.
    'photo_stage' => 2,

    // Function tests per item category (lower case); "default" for the others.
    'tests' => [
        'light' => ['power', 'pan_tilt', 'leds', 'zoom', 'dmx'],
        'lighting' => ['power', 'pan_tilt', 'leds', 'zoom', 'dmx'],
        'audio' => ['power', 'sound', 'inputs', 'dsp'],
        'sound' => ['power', 'sound', 'inputs', 'dsp'],
        'video' => ['power', 'image', 'pixels', 'signal'],
        'default' => ['power', 'function'],
    ],

    // Return costs: days the customer has to react before the costs are invoiced.
    'costs_days' => 7,

    'damage_types' => ['housing', 'lens', 'connector', 'display', 'other'],

    // How many days ahead the phone shows jobs to pick, and returns due.
    'pick_days' => 2,
];
