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
    'cleaning' => [1 => 0, 2 => 1500, 3 => 3000],

    // A photo is required for cleaning stage 3, and at the check when something is missing, broken or damaged.
    'photo_stage' => 3,

    // Function tests per item category (lower case); "default" for the others.
    'tests' => [
        'light' => ['power', 'pan_tilt', 'leds', 'zoom', 'dmx'],
        'lighting' => ['power', 'pan_tilt', 'leds', 'zoom', 'dmx'],
        'audio' => ['power', 'sound', 'inputs', 'dsp'],
        'sound' => ['power', 'sound', 'inputs', 'dsp'],
        'video' => ['power', 'image', 'pixels', 'signal'],
        'default' => ['power', 'function'],
    ],

    'damage_types' => ['housing', 'lens', 'connector', 'display', 'other'],

    // How many days ahead the phone shows jobs to pick, and returns due.
    'pick_days' => 2,
];
