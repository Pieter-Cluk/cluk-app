<?php

namespace App\Support;

use Illuminate\Support\Collection;

/**
 * Subtitles on quotes and orders: a text line marked as heading starts a part (Audio, Light, Staging…).
 * The subtotal of a part is the sum of its lines up to the next subtitle.
 */
class LineSections
{
    /**
     * Subtotal per part, keyed by the id of the LAST line of the part (that is where the subtotal row goes).
     *
     * @return array<int, array{name: string, cents: int}>
     */
    public static function totals(Collection $lines): array
    {
        $out = [];
        $current = null;
        $flush = function () use (&$current, &$out) {
            if ($current && $current['last'] !== null) {
                $out[$current['last']] = ['name' => $current['name'], 'cents' => $current['cents']];
            }
        };
        foreach ($lines as $l) {
            if ($l->kind === 'text' && $l->heading) {
                $flush();
                $current = ['name' => $l->name, 'cents' => 0, 'last' => null]; // a subtitle without lines gets no subtotal
            } elseif ($current) {
                $current['cents'] += $l->kind === 'text' ? 0 : (int) $l->amount_cents;
                $current['last'] = $l->id;
            }
        }
        $flush();

        return $out;
    }
}
