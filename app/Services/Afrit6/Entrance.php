<?php

namespace App\Services\Afrit6;

use App\Models\Afrit6\Guest;
use App\Models\Afrit6\RoomBooking;
use App\Models\Website\Slide;
use Illuminate\Support\Collection;

/**
 * The big TV at the entrance of Afrit 6: a slideshow (Media › Website › Afrit 6 screen) with the day's agenda
 * on the side: the guests members expect (welcomed by name only when the member allows it) and the meeting room.
 * Room booking notes and guest notes are never shown.
 */
class Entrance
{
    /** Seconds a picture stays on the TV (videos play to the end). */
    public const SECONDS = 10;

    public static function slides(): Collection
    {
        return Slide::where('place', 'afrit6_screen')->live()->orderBy('position')->orderBy('id')->get(['id', 'kind', 'path', 'poster_path', 'texts', 'alt']);
    }

    /** Changes when a slide is added, removed or changed: the screen then reloads itself. */
    public static function slidesVersion(): string
    {
        return substr(md5(self::slides()->map(fn ($s) => $s->id.':'.$s->path)->implode('|').'|'.Slide::where('place', 'afrit6_screen')->max('updated_at')), 0, 12);
    }

    public static function guests(): Collection
    {
        return Guest::with('member.company')->live()->whereDate('date', today())->get()
            ->sortBy(fn (Guest $g) => $g->time ?? '99:99')->values()
            ->map(fn (Guest $g) => [
                'time' => $g->time,
                'name' => $g->on_screen ? $g->name : null,
                'company' => $g->on_screen ? $g->company : null,
                'host' => $g->member?->companyName() ?: $g->member?->name,
                'past' => $g->time !== null && $g->time < now()->subHour()->format('H:i'),
            ]);
    }

    public static function rooms(): Collection
    {
        return RoomBooking::with(['member.company', 'space'])->live()->whereDate('date', today())->orderBy('start_slot')->get()
            ->map(fn (RoomBooking $b) => [
                'time' => $b->timeRange(),
                'room' => $b->space?->code,
                'who' => $b->member?->companyName() ?: $b->member?->name,
                'now' => $b->hasStarted() && ! $b->startsAt()->addMinutes($b->slots * 30)->isPast(),
                'done' => $b->startsAt()->addMinutes($b->slots * 30)->isPast(),
            ]);
    }
}
