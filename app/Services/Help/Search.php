<?php

namespace App\Services\Help;

use App\Models\Help\Article;
use Illuminate\Support\Collection;

/**
 * Finds help articles for a question in plain words ("the label printer prints
 * nothing"): every meaningful word counts, a hit in the title counts more, and
 * the start of a longer word also counts (print / printing / printer).
 * All four languages and the keywords are searched together.
 */
class Search
{
    public const STOP = ['the', 'a', 'an', 'and', 'or', 'of', 'to', 'in', 'on', 'for', 'is', 'it', 'i', 'my', 'how', 'do', 'does', 'can', 'what', 'with', 'not', 'doesnt', 'dont', 'cant', 'when', 'where', 'why', 'be', 'me', 'we',
        'de', 'het', 'een', 'en', 'of', 'van', 'te', 'op', 'voor', 'is', 'ik', 'mijn', 'hoe', 'wat', 'met', 'niet', 'kan', 'waar', 'waarom', 'wanneer', 'je', 'we', 'er', 'die', 'dat',
        'le', 'la', 'les', 'un', 'une', 'et', 'ou', 'du', 'des', 'pour', 'est', 'je', 'mon', 'ma', 'comment', 'quoi', 'avec', 'pas', 'ne', 'que', 'qui',
        'el', 'los', 'las', 'uno', 'una', 'y', 'o', 'del', 'para', 'es', 'yo', 'mi', 'como', 'que', 'con', 'no', 'se', 'por'];

    /** @return list<string> */
    public static function words(string $q): array
    {
        $words = preg_split('/[^a-z0-9]+/', Article::fold($q), -1, PREG_SPLIT_NO_EMPTY);

        return array_values(array_unique(array_filter($words, fn ($w) => strlen($w) >= 2 && ! in_array($w, self::STOP, true))));
    }

    public static function score(Article $a, array $words): int
    {
        $hay = $a->haystack();
        $title = Article::fold(implode(' ', array_values($a->title ?? [])).' '.$a->keywords);
        $score = 0;
        foreach ($words as $w) {
            if (preg_match('/\b'.preg_quote($w, '/').'/', $hay)) {
                $score += 2 + (str_contains($title, $w) ? 3 : 0);
            } elseif (strlen($w) >= 5 && str_contains($hay, substr($w, 0, 4))) {
                $score += 1;
            }
        }

        return $score;
    }

    /** @param Collection<int, Article> $articles */
    public static function rank(Collection $articles, string $q): Collection
    {
        $words = self::words($q);
        if (! $words) {
            return $articles;
        }

        return $articles->map(fn (Article $a) => [$a, self::score($a, $words)])->filter(fn ($x) => $x[1] > 0)
            ->sortByDesc(fn ($x) => $x[1])->map(fn ($x) => $x[0])->values();
    }
}
