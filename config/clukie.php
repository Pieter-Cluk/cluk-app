<?php

/*
| Clukie: the chat helper on the sales pages of the website.
| Answers with Claude (ANTHROPIC_API_KEY) from the sales catalogue and hands the
| conversation to the team (Incoming) when a person is needed.
*/
return [
    'enabled' => (bool) env('CLUKIE_ENABLED', true),

    // When the team answers chats (Europe/Brussels). Days: 1 = Monday … 7 = Sunday.
    'timezone' => 'Europe/Brussels',
    'days' => [1, 2, 3, 4, 5],
    'from' => '09:00',
    'until' => '18:30',

    // Limits per conversation.
    'max_messages' => 60,
    'max_length' => 1000,
    'history' => 20,

    // Animation clips per mood (paths under public/, MP4/WebM on a black background).
    // A mood without a clip is animated by CSS on the picture.
    'clips' => [
        'idle' => 'images/clukie/idle.mp4',
        'wave' => 'images/clukie/wave.mp4',
        'think' => 'images/clukie/think.mp4',   // while Clukie looks something up (loops)
        'thumbs' => 'images/clukie/thumbs.mp4', // product cards shown, added to the basket, handed over
        'hop' => 'images/clukie/hop.mp4',       // when Clukie first appears on the page
    ],
];
