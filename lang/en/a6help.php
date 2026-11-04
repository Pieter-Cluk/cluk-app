<?php

// Afrit 6 member help: titles, intros and the steps of each video (same order as config/afrit6-help.php).
return [
    'title' => 'Help',
    'intro' => 'Short videos that show how to do everything in your Afrit 6 portal. Pick what you want to do.',
    'steps_title' => 'Step by step',
    'watch' => 'Watch the video',
    'back' => 'All help',
    'video_note' => '',
    'ask' => 'Can\'t find it? Ask Clukie, bottom right.',
    'outro' => 'That\'s it! You find more help under Help, or just ask Clukie.',
    'topics' => [
        'tour' => [
            'title' => 'Your portal in one minute',
            'intro' => 'What you find where in your Afrit 6 portal.',
            'steps' => [
                'Welcome to your Afrit 6 portal. This is where you arrange everything for your workplace.',
                'At the top are the tabs: Desks, Meeting room, Guests, Warehouse services and My bill.',
                'Your door code is always on the overview. Click Show to see it.',
                'Here you see your own desks.',
                'And your next meeting room bookings, with your free moments this week.',
                'At the bottom you see what you have used so far this month.',
                'Questions? Under Help you find all the videos, and Clukie helps you bottom right.',
            ],
        ],
        'desk_day' => [
            'title' => 'A desk for half a day or a day',
            'intro' => 'How to book a free desk for a morning, an afternoon or a whole day.',
            'steps' => [
                'Go to the Desks tab.',
                'At the top you book a desk for a morning, an afternoon or a whole day.',
                'Pick the date and the part of the day.',
                'Click Show free desks.',
                'Click a free desk. It is booked right away.',
                'Your booking shows below. As long as it hasn\'t started, you can still cancel it.',
            ],
        ],
        'fixed_desk' => [
            'title' => 'Your fixed desk: swap or add one',
            'intro' => 'How to move to another desk or ask for an extra fixed desk.',
            'steps' => [
                'Under Fixed desks per month you see all desks.',
                'Green is your desk. Grey is rented out, a dotted line means free.',
                'Click a free desk.',
                'Want to move? Click Swap. From tomorrow you sit there, at the same price.',
                'Or ask for it as an extra desk. Pick from when and click Ask for this desk.',
                'Rather have your own office? Ask for a price under Private office.',
            ],
        ],
        'room' => [
            'title' => 'Book the meeting room',
            'intro' => 'How to book the meeting room per half hour.',
            'steps' => [
                'Every week you get one free moment of up to 2 hours per desk.',
                'Pick the day at the top.',
                'The day plan shows which moments are free.',
                'Pick the setup: meeting or classroom.',
                'Pick the start time.',
                'Pick how long you need the room.',
                'Click Book.',
                'Your booking shows under Your bookings. You can cancel it there too.',
            ],
        ],
        'guests' => [
            'title' => 'Announce your guests',
            'intro' => 'How to let us know who is coming to visit.',
            'steps' => [
                'Go to the Guests tab.',
                'Pick the date and time of the visit.',
                'Type your guest\'s name. More guests? One name per line.',
                'Add their company if you like.',
                'Tick whether the name may appear on the welcome screen at the entrance.',
                'Click Add.',
                'Your guests show under Expected guests.',
            ],
        ],
        'storage' => [
            'title' => 'Storage: a shelf or pallet place',
            'intro' => 'How to ask for storage in the warehouse.',
            'steps' => [
                'Go to Warehouse services. On the right you start a new request.',
                'Pick Shelves. For bigger things, pick Pallet places.',
                'Fill in how many you need and from when.',
                'Add a note if you like.',
                'Click Send request. The Afrit 6 team confirms your request.',
                'Under Your requests you follow everything up. You pay per day used and stop the rental here yourself.',
            ],
        ],
        'parcels' => [
            'title' => 'Receive and send parcels',
            'intro' => 'How to let us know goods are coming in or going out.',
            'steps' => [
                'Under Warehouse services, open Receive goods.',
                'Fill in how many parcels or pallets are coming, and when.',
                'Choose: on the 24-hour pick-up shelf, or straight into your storage.',
                'Click Send request. The team receives everything for you.',
                'Sending something? Use Send goods and fill in the carrier.',
                'We can also pack for you: pick Packing.',
            ],
        ],
        'door_bill' => [
            'title' => 'Your door code and your bill',
            'intro' => 'Where to find your door code and your running bill.',
            'steps' => [
                'On the overview, click Show next to Door code.',
                'Need a new code? Request it here.',
                'Go to the My bill tab.',
                'Here you see your running bill for this contract month, excluding VAT.',
                'Below are your previous months.',
            ],
        ],
    ],
];
