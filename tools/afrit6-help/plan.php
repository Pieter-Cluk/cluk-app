<?php

/*
 * Prints the filming plan for one language as JSON: per topic the start page, the steps (highlight, action, text)
 * and the title/outro texts. Used by film.cjs. Run: php artisan tinker --execute="\$lang='nl'; require 'tools/afrit6-help/plan.php';"
 */
app()->setLocale($lang);
$base = rtrim(config('app.url'), '/');
$rel = fn (string $route) => str_replace($base, '', route($route));
$out = ['lang' => $lang, 'outro' => __('a6help.outro'), 'topics' => []];
foreach (config('afrit6-help.topics') as $key => $t) {
    $texts = (array) __("a6help.topics.{$key}.steps");
    $out['topics'][] = [
        'key' => $key, 'page' => $rel($t['page']), 'title' => __("a6help.topics.{$key}.title"), 'intro' => __("a6help.topics.{$key}.intro"),
        'steps' => array_map(function ($s, $i) use ($texts, $rel) {
            $do = $s['do'] ?? [];
            if (isset($do['goto'])) {
                $do['goto'] = $rel($do['goto']);
            }

            return ['el' => $s['el'], 'nth' => $s['nth'] ?? 0, 'do' => (object) $do, 'text' => $texts[$i]];
        }, $t['steps'], array_keys($t['steps'])),
    ];
}
file_put_contents(getenv('PLAN_OUT') ?: 'php://stdout', json_encode($out, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
