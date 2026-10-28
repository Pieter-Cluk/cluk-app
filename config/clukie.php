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
    // Empty: the picture is animated by CSS. Fill in when the 3D clips are ready.
    'clips' => [
        // 'idle' => 'images/clukie/idle.mp4', 'wave' => …, 'think' => …, 'thumbs' => …, 'sleep' => …, 'hop' => …
    ],
];
