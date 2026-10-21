<?php

/*
|--------------------------------------------------------------------------
| Document families
|--------------------------------------------------------------------------
| Every document CLUK makes, with its numbering. "customer" documents carry the
| commercial note and the seasonal message; "prices" says whether they show money.
| "built" says whether the app makes it already.
*/

return [
    'families' => [
        'rental' => [
            'R-QUO' => ['prices' => true, 'customer' => true, 'signs' => 2, 'built' => true],
            'R-OC' => ['prices' => true, 'customer' => true, 'signs' => 2, 'built' => true],
            'R-PICK' => ['prices' => false, 'customer' => false, 'signs' => 2, 'built' => true],
            'R-RET' => ['prices' => false, 'customer' => false, 'signs' => 2, 'built' => true],
            'R-DN' => ['prices' => false, 'customer' => true, 'signs' => 2, 'built' => true],
            'R-PF' => ['prices' => true, 'customer' => true, 'signs' => 0, 'built' => true],
            'R-CH' => ['prices' => true, 'customer' => true, 'signs' => 0, 'built' => true],
        ],
        'sales' => [
            'S-QUO' => ['prices' => true, 'customer' => true, 'signs' => 2, 'built' => true],
            'S-OC' => ['prices' => true, 'customer' => true, 'signs' => 2, 'built' => true],
            'S-PICK' => ['prices' => false, 'customer' => false, 'signs' => 2, 'built' => true],
            'S-DN' => ['prices' => false, 'customer' => true, 'signs' => 2, 'built' => true],
            'S-PF' => ['prices' => true, 'customer' => true, 'signs' => 0, 'built' => true],
        ],
        'subhire' => [
            'SHQ' => ['prices' => true, 'customer' => false, 'signs' => 0, 'built' => true],
            'SHC' => ['prices' => true, 'customer' => false, 'signs' => 2, 'built' => true],
        ],
        'purchase' => [
            'PQ' => ['prices' => true, 'customer' => false, 'signs' => 0, 'built' => true],
            'PO' => ['prices' => true, 'customer' => false, 'signs' => 2, 'built' => true],
        ],
        'venue' => [
            'F-BC' => ['prices' => true, 'customer' => true, 'signs' => 2, 'built' => true],
            'F-PF' => ['prices' => true, 'customer' => true, 'signs' => 0, 'built' => true],
        ],
        'afrit' => [
            'A6S' => ['prices' => true, 'customer' => true, 'signs' => 0, 'built' => true],
        ],
    ],
];
