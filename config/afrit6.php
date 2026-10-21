<?php

/*
|--------------------------------------------------------------------------
| Afrit 6 co-working: prices and rules
|--------------------------------------------------------------------------
| Amounts in cents, excluding VAT, all confirmed by Pieter on 21 Oct 2026.
| They will move to a settings screen.
*/

return [

    'prices' => [
        'desk_month' => 35000,        // fixed desk, per contract month (2 screens, keyboard, mouse, internet)
        'desk_half_day' => 3000,      // free desk for a morning or an afternoon
        'desk_day' => 5000,           // free desk for a whole day
        'shelf_day' => 50,            // shelf 55 × 100 × 55 cm, per shelf per day
        'pallet_day' => 100,          // pallet place, per place per day
        'packing_coli' => 160,        // packing labour per coli (3–5 min)
        'packing_pallet' => 1500,     // packing labour per pallet (15 min)
        'handling_coli' => 200,       // receive into storage / send: per coli
        'handling_pallet' => 800,     // receive into storage / send: per pallet
        'room_half_hour' => 625,      // meeting room beyond the free weekly slot: € 12.50 per hour
    ],

    // Day bookings of a free desk.
    'desk_parts' => [
        'am' => ['08:00', '13:00'],
        'pm' => ['13:00', '18:00'],
        'day' => ['08:00', '18:00'],
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
    'member_kinds' => ['shelf', 'pallet', 'packing', 'receive', 'send', 'desk_add', 'desk_swap', 'office'],
    'staff_kinds' => ['room_reset'],

];
