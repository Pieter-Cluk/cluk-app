<?php

namespace App\Services\Products;

use Illuminate\Database\Eloquent\Builder;

/**
 * Item search for quotes: every word must match somewhere, in the number,
 * name, brand, type, search words or one of the item's attribute values in any
 * language. "NH34 zwart 2m" finds the black 2 m NH34 section.
 */
class ItemSearch
{
    public static function words(Builder $query, string $q): Builder
    {
        foreach (array_slice(preg_split('/\s+/', trim($q), -1, PREG_SPLIT_NO_EMPTY), 0, 6) as $word) {
            $like = '%'.mb_strtolower($word).'%';
            $value = rtrim(str_replace(',', '.', mb_strtolower($word)), 'm'); // "2,5m" → "2.5"
            $query->where(fn ($w) => $w->whereRaw('lower(number) like ?', [$like])->orWhereRaw('lower(name) like ?', [$like])
                ->orWhereRaw('lower(coalesce(brand, \'\')) like ?', [$like])->orWhereRaw('lower(coalesce(type, \'\')) like ?', [$like])
                ->orWhereRaw('lower(coalesce(search_words, \'\')) like ?', [$like])
                ->orWhereHas('options', fn ($o) => $o->whereRaw('lower(value) = ?', [$value !== '' ? $value : mb_strtolower($word)])->orWhereRaw('lower(coalesce(label, \'\')) like ?', [$like])));
        }

        return $query;
    }
}
