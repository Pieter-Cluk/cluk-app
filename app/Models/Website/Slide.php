<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A picture or video on the website, with where a click goes. One active per place = static; more = slideshow. */
#[Fillable(['place', 'position', 'kind', 'path', 'poster_path', 'mobile_path', 'link_type', 'link_value', 'texts', 'alt', 'active', 'starts_on', 'ends_on', 'updated_by_id'])]
class Slide extends Model
{
    protected $table = 'web_slides';

    /** afrit6_screen = the big TV at the entrance of Afrit 6 (not on the website). */
    public const PLACES = ['home_hero', 'dryhire_hero', 'sales_slider', 'afrit6_screen'];

    public const LINKS = ['none', 'page', 'dryhire_item', 'dryhire_cat', 'sales_item', 'sales_cat', 'brand', 'url'];

    public const PAGES = ['home', 'dryhire', 'sales', 'sales_new', 'brands', 'news', 'factory', 'afrit6', 'account', 'register'];

    /**
     * The ideal picture per spot. The frame on the website changes shape with the
     * screen (measured from phone 390 px to a wide 1920 px screen); 'frames' are those
     * shapes (width ÷ height). The picture is always cropped from the middle, so the
     * part that every screen shows is the "safe zone".
     */
    public const FORMATS = [
        'home_hero' => ['w' => 2400, 'h' => 2000, 'frames' => ['phone' => 1.30, 'tablet' => 0.74, 'laptop' => 1.19, 'wide' => 1.85], 'note' => 'bw'],
        'dryhire_hero' => ['w' => 2400, 'h' => 1400, 'frames' => ['phone' => 0.94, 'laptop' => 1.75, 'wide' => 2.33], 'note' => 'dark_left'],
        'sales_slider' => ['w' => 2400, 'h' => 880, 'frames' => ['phone' => 0.93, 'tablet' => 1.85, 'laptop' => 2.74], 'note' => 'text_left'],
        'afrit6_screen' => ['w' => 1920, 'h' => 1080, 'frames' => ['tv' => 1.78], 'note' => 'panel_right'],
        'tile' => ['w' => 1200, 'h' => 1000, 'frames' => ['tablet' => 0.91, 'laptop' => 1.17, 'phone' => 1.46], 'note' => 'tile'],
    ];

    /** The part of a crop shape (width ÷ height) inside the picture, as fractions [w, h]. */
    public static function crop(string $format, float $ratio): array
    {
        $f = self::FORMATS[$format];
        $pic = $f['w'] / $f['h'];

        return $ratio < $pic ? [$ratio / $pic, 1.0] : [1.0, $pic / $ratio];
    }

    /** The middle part every screen shows, in px [w, h]. */
    public static function safe(string $format): array
    {
        $f = self::FORMATS[$format];
        $w = min(array_map(fn ($r) => self::crop($format, $r)[0], $f['frames']));
        $h = min(array_map(fn ($r) => self::crop($format, $r)[1], $f['frames']));

        return [(int) (floor($f['w'] * $w / 10) * 10), (int) (floor($f['h'] * $h / 10) * 10)];
    }

    /** The ideal size of the separate phone picture: the phone frame of the spot, 1200 px wide. */
    public static function phoneSize(string $format): ?array
    {
        $r = self::FORMATS[$format]['frames']['phone'] ?? null;

        return $r ? [1200, (int) (round(1200 / $r / 10) * 10)] : null;
    }

    /** Ratio as a short text, e.g. 6:5. */
    public static function ratioText(string $format): string
    {
        $f = self::FORMATS[$format];
        [$a, $b] = [$f['w'], $f['h']];
        while ($b) {
            [$a, $b] = [$b, $a % $b];
        }
        $d = $a;

        return ($f['w'] / $d).':'.($f['h'] / $d);
    }

    protected function casts(): array
    {
        return ['texts' => 'array', 'active' => 'boolean', 'starts_on' => 'date', 'ends_on' => 'date', 'position' => 'integer'];
    }

    /** Shown today: switched on and inside its dates. */
    public function scopeLive(Builder $q): Builder
    {
        $d = today()->toDateString();

        return $q->where('active', true)
            ->where(fn ($w) => $w->whereNull('starts_on')->orWhereDate('starts_on', '<=', $d))
            ->where(fn ($w) => $w->whereNull('ends_on')->orWhereDate('ends_on', '>=', $d));
    }

    public function isLive(): bool
    {
        return $this->active && (! $this->starts_on || ! $this->starts_on->isFuture()) && (! $this->ends_on || ! $this->ends_on->isPast());
    }

    /** A text in the visitor's language, else English. */
    public function text(string $field, ?string $locale = null): ?string
    {
        $t = $this->texts ?? [];
        $locale ??= app()->getLocale();

        return ($t[$locale][$field] ?? null) ?: (($t['en'][$field] ?? null) ?: null);
    }
}
