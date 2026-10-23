<?php

namespace App\Models\Product;

/** Names kept per app language; English is the fallback. */
trait Translated
{
    public function label(?string $locale = null, string $field = 'name'): string
    {
        $v = $this->{$field} ?? [];
        $locale ??= app()->getLocale();

        return (string) (($v[$locale] ?? '') !== '' ? $v[$locale] : ($v['en'] ?? ''));
    }
}
