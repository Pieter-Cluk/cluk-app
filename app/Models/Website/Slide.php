<?php

namespace App\Models\Website;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** A picture or video on the website, with where a click goes. One active per place = static; more = slideshow. */
#[Fillable(['place', 'position', 'kind', 'path', 'poster_path', 'link_type', 'link_value', 'texts', 'alt', 'active', 'starts_on', 'ends_on', 'updated_by_id'])]
class Slide extends Model
{
    protected $table = 'web_slides';

    public const PLACES = ['home_hero', 'dryhire_hero', 'sales_slider'];

    public const LINKS = ['none', 'page', 'dryhire_item', 'dryhire_cat', 'sales_item', 'sales_cat', 'brand', 'url'];

    public const PAGES = ['home', 'dryhire', 'sales', 'sales_new', 'brands', 'news', 'factory', 'afrit6', 'account', 'register'];

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
