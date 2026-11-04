<?php

/*
 * Help for Afrit 6 members: short instruction videos (Dutch and English) with the steps written out.
 * The texts are in lang/{nl,en,fr,es}/a6help.php (one text per step, same order as here).
 * The steps below tell the filming script (tools/afrit6-help/film.cjs) where to look and what to do:
 *   page: the portal page to start on · el: what to highlight (CSS, nth = which one) · do: what to do after the frame.
 * Videos: public/media/afrit6-help/{nl,en}/{key}.mp4 (+ .jpg poster). FR and ES members see the English video.
 */

return [
    'video_langs' => ['nl', 'en'],

    'topics' => [
        'tour' => ['page' => 'afrit6.portal', 'icon' => '◎', 'steps' => [
            ['el' => '.a6-logo, .p-top .a6-brand'],
            ['el' => 'nav.p-tabs'],
            ['el' => 'main section.card', 'nth' => 0],
            ['el' => 'main section.card', 'nth' => 1],
            ['el' => 'main section.card', 'nth' => 2],
            ['el' => 'main section.card', 'nth' => 4],
            ['el' => "nav.p-tabs a[href$='/help']"],
        ]],
        'desk_day' => ['page' => 'afrit6.portal.desks', 'icon' => '▢', 'steps' => [
            ['el' => 'nav.p-tabs a', 'nth' => 1],
            ['el' => 'main section.card-accent'],
            ['el' => 'main section.card-accent form[method=get]', 'do' => ['fill_date' => 'main section.card-accent input[name=day]', 'select' => ['select[name=part]', 'am']]],
            ['el' => 'main section.card-accent form[method=get] button', 'do' => ['click' => 'main section.card-accent form[method=get] button']],
            ['el' => 'main section.card-accent .desk-row form', 'nth' => 0, 'do' => ['click' => 'main section.card-accent .desk-row form button']],
            ['el' => 'main section.card-accent .p-line', 'nth' => 0],
        ]],
        'fixed_desk' => ['page' => 'afrit6.portal.desks', 'icon' => '▣', 'scroll' => 'main .split', 'steps' => [
            ['el' => 'main .split > section.card'],
            ['el' => 'a.desk.d-mine'],
            ['el' => 'a.desk.d-free', 'nth' => 0, 'do' => ['click' => 'a.desk.d-free']],
            ['el' => 'aside section.card-accent form', 'nth' => 0],
            ['el' => 'aside section.card-accent form', 'nth' => 1],
            ['el' => 'aside section.card:not(.card-accent)', 'nth' => 0],
        ]],
        'room' => ['page' => 'afrit6.portal.room', 'icon' => '◫', 'steps' => [
            ['el' => '.slot-count'],
            ['el' => 'nav.chips a', 'nth' => 1, 'do' => ['click' => 'nav.chips a >> nth=1']],
            ['el' => 'main .slots'],
            ['el' => '#book-form fieldset'],
            ['el' => '#book-start', 'do' => ['select' => ['#book-start', '10']]],
            ['el' => '#book-form select[name=slots]', 'do' => ['select' => ['#book-form select[name=slots]', '4']]],
            ['el' => '#book-form button.primary', 'do' => ['click' => '#book-form button.primary']],
            ['el' => 'main aside section.card', 'nth' => 1],
        ]],
        'guests' => ['page' => 'afrit6.portal.guests', 'icon' => '☺', 'steps' => [
            ['el' => 'nav.p-tabs a', 'nth' => 3],
            ['el' => 'main form input[name=date]', 'do' => ['fill_date' => 'main form input[name=date]', 'fill' => ['main form input[name=time]', '10:30']]],
            ['el' => 'main form textarea[name=names]', 'do' => ['type' => ['main form textarea[name=names]', 'Emma Janssens']]],
            ['el' => 'main form input[name=company]', 'do' => ['type' => ['main form input[name=company]', 'Atelier Noord']]],
            ['el' => 'main form input[name=on_screen][type=checkbox]'],
            ['el' => 'main form button.primary', 'do' => ['click' => 'main form button.primary']],
            ['el' => 'main section.card', 'nth' => 1],
        ]],
        'storage' => ['page' => 'afrit6.portal.services', 'icon' => '▤', 'steps' => [
            ['el' => 'aside section.card-accent'],
            ['el' => 'details.svc', 'nth' => 0, 'do' => ['click' => 'details.svc >> nth=0 >> summary']],
            ['el' => 'details.svc[open] form', 'do' => ['fill' => ['details.svc[open] input[name=qty]', '2']]],
            ['el' => 'details.svc[open] textarea[name=note]', 'do' => ['type' => ['details.svc[open] textarea[name=note]', 'Voor beursmateriaal']]],
            ['el' => 'details.svc[open] button.primary', 'do' => ['click' => 'details.svc[open] button.primary']],
            ['el' => 'main .split > div, main > .split > section', 'nth' => 0],
        ]],
        'parcels' => ['page' => 'afrit6.portal.services', 'icon' => '⇄', 'steps' => [
            ['el' => 'details.svc', 'nth' => 3, 'do' => ['click' => 'details.svc >> nth=3 >> summary']],
            ['el' => 'details.svc[open] form', 'do' => ['fill' => ['details.svc[open] input[name=colis]', '3']]],
            ['el' => 'details.svc[open] fieldset.radios'],
            ['el' => 'details.svc[open] button.primary'],
            ['el' => 'details.svc', 'nth' => 4],
            ['el' => 'details.svc', 'nth' => 2],
        ]],
        'door_bill' => ['page' => 'afrit6.portal', 'icon' => '€', 'steps' => [
            ['el' => 'main section.card', 'nth' => 0, 'do' => ['click' => 'main section.card >> nth=0 >> details.door summary']],
            ['el' => 'main section.card details.mt summary', 'nth' => 0],
            ['el' => 'nav.p-tabs a', 'nth' => 5, 'do' => ['goto' => 'afrit6.portal.bill']],
            ['el' => 'main section.card', 'nth' => 0],
            ['el' => 'main section.card', 'nth' => 1],
        ]],
    ],
];
