<?php

namespace App\Http\Controllers\Afrit6;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** Help for members: short instruction videos (Dutch or English) with the steps written out in their language. */
class HelpController extends Controller
{
    public function index(Request $request): View
    {
        return view('afrit6.portal.help', ['topics' => self::topics(), 'topic' => null, 'video' => self::videoLang()]);
    }

    public function show(Request $request, string $topic): View
    {
        $topics = self::topics();
        abort_unless($topics->has($topic), 404);

        return view('afrit6.portal.help', ['topics' => $topics, 'topic' => $topic, 'video' => self::videoLang()]);
    }

    /** key => [title, intro, steps, video url or null, poster url or null] */
    public static function topics()
    {
        $lang = self::videoLang();

        return collect(config('afrit6-help.topics'))->map(function ($t, $key) use ($lang) {
            $file = "media/afrit6-help/{$lang}/{$key}";

            return [
                'key' => $key, 'icon' => $t['icon'] ?? '•', 'title' => __("a6help.topics.{$key}.title"), 'intro' => __("a6help.topics.{$key}.intro"),
                'steps' => (array) __("a6help.topics.{$key}.steps"),
                'video' => is_file(public_path($file.'.mp4')) ? asset($file.'.mp4').'?v='.filemtime(public_path($file.'.mp4')) : null,
                'poster' => is_file(public_path($file.'.jpg')) ? asset($file.'.jpg').'?v='.filemtime(public_path($file.'.jpg')) : null,
            ];
        });
    }

    /** Dutch for Dutch-speaking members, English for everyone else. */
    public static function videoLang(): string
    {
        return app()->getLocale() === 'nl' ? 'nl' : 'en';
    }
}
