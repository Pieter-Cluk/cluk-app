<?php

/*
|--------------------------------------------------------------------------
| Afrit 6 co-working: prices and rules
|--------------------------------------------------------------------------
| Amounts in cents, excluding VAT. Values marked "sample" in the mockups
| still need Pieter's confirmation; they will move to a settings screen.
*/

return [

    'prices' => [
        'desk_month' => 35000,        // fixed desk, per contract month (2 screens, keyboard, mouse, internet)
        'shelf_day' => 50,            // shelf 55 × 100 × 55 cm, per shelf per day
        'pallet_day' => 100,          // pallet place, per place per day
        'packing_coli' => 250,        // packing labour per coli (3–5 min)
        'packing_pallet' => 1500,     // packing labour per pallet (15 min)
        'handling_coli' => 200,       // receive into storage / send: per coli (sample)
        'handling_pallet' => 800,     // receive into storage / send: per pallet (sample)
        'room_half_hour' => 1500,     // meeting room beyond the free weekly slot (sample: € 30 per hour)
    ],

    'room' => [
        'opens' => '08:00',
        'slots' => 20,                // 20 half hours: 08:00 – 18:00
        'weeks_ahead' => 8,           // how far ahead members can book
        'free_half_hours' => 4,       // one free booking of up to 2 h per desk per week
        'max_half_hours' => 20,
        'setups' => [
            'meeting' => 12,          // seats
            'classroom' => 20,        // + teacher; needs a 30 min reset before and after
        ],
    ],

    // What members can ask for in the portal (and the staff task kinds).
    'member_kinds' => ['shelf', 'pallet', 'packing', 'receive', 'send', 'desk_add', 'desk_swap'],
    'staff_kinds' => ['room_reset'],

];
