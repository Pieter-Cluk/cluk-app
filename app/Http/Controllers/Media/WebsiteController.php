<?php

namespace App\Http\Controllers\Media;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Website\Slide;
use App\Services\Media\Translator;
use App\Services\Website\SiteSettings;
use App\Support\Images;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Media › Website: the pictures, videos and texts of the homepage, dry-hire and
 * sales pages. One picture = a still; more = a slideshow; every picture or video
 * says where a click goes. The Factory and Afrit 6 pages are not changed here.
 * Changing needs the right "Website: change pictures and texts"; every change is logged.
 */
class WebsiteController extends Controller
{
    public const TABS = ['home', 'dryhire', 'sales', 'screen', 'downloads', 'general'];

    public const PLACE_OF = ['home' => 'home_hero', 'dryhire' => 'dryhire_hero', 'sales' => 'sales_slider', 'screen' => 'afrit6_screen'];

    public const VIDEO_MAX_KB = 61440; // 60 MB

    public function index(Request $request): View
    {
        $tab = in_array($request->query('tab'), self::TABS, true) ? $request->query('tab') : 'home';
        $place = self::PLACE_OF[$tab] ?? null;

        return view('media.website.index', [
            'tab' => $tab, 'place' => $place,
            'slides' => $place ? Slide::where('place', $place)->orderBy('position')->orderBy('id')->get() : collect(),
            'canEdit' => $request->user()->hasRight('website.edit'),
            'claude' => Translator::available(),
            'locales' => array_keys(config('cluk.locales')),
        ]);
    }

    /** Page texts per language (the keys of this tab). */
    public function texts(Request $request, string $tab): RedirectResponse
    {
        $this->can($request);
        $keys = array_values(array_filter(array_keys(SiteSettings::TEXTS), fn ($k) => str_starts_with($k, $tab.'.')));
        abort_if($keys === [], 404);
        $d = $request->validate(['t' => ['array'], 't.*' => ['array'], 't.*.*' => ['nullable', 'string', 'max:400']]);
        $texts = [];
        foreach ($keys as $k) {
            $texts[$k] = array_filter(array_map(fn ($v) => trim((string) $v), $d['t'][str_replace('.', '__', $k)] ?? []), fn ($v) => $v !== '');
        }
        $texts = $this->translate($request, $texts);
        foreach ($texts as $k => $v) {
            SiteSettings::put($k, $v ?: null, $request->user()->id);
        }
        AuditLog::record('website.texts', null, ['page' => $tab], $request->user());

        return $this->back($tab, __('website.flash.texts_saved'));
    }

    /** A homepage tile: texts and its picture. */
    public function tile(Request $request, string $tile): RedirectResponse
    {
        $this->can($request);
        abort_unless(isset(SiteSettings::TILES[$tile]), 404);
        $d = $request->validate(['text' => ['array'], 'text.*' => ['nullable', 'string', 'max:200'], 'cta' => ['array'], 'cta.*' => ['nullable', 'string', 'max:80'],
            'image' => ['nullable', ...Images::rule()], 'remove_image' => ['boolean']]);
        $old = SiteSettings::get('home.tile.'.$tile, []);
        $clean = fn ($a) => array_filter(array_map(fn ($v) => trim((string) $v), $a ?? []), fn ($v) => $v !== '');
        $t = $this->translate($request, ['text' => $clean($d['text'] ?? []), 'cta' => $clean($d['cta'] ?? [])]);
        $image = $old['image'] ?? null;
        if ($request->hasFile('image')) {
            $this->drop($image);
            $image = Images::store($request->file('image'), 'website', 1600);
        } elseif ($request->boolean('remove_image')) {
            $this->drop($image);
            $image = null;
        }
        SiteSettings::put('home.tile.'.$tile, ['text' => $t['text'], 'cta' => $t['cta'], 'image' => $image], $request->user()->id);
        AuditLog::record('website.tile', null, ['tile' => $tile], $request->user());
        $warn = $request->hasFile('image') ? self::fitWarning($image, 'tile') : null;

        return $this->back('home', __('website.flash.tile_saved').($warn ? ' '.$warn : ''), 'tiles');
    }

    /** Popular searches (dry-hire) and the general settings. */
    public function general(Request $request): RedirectResponse
    {
        $this->can($request);
        $d = $request->validate([
            'popular' => ['nullable', 'string', 'max:500'],
            'slide_seconds' => ['nullable', 'integer', 'min:3', 'max:30'],
            'instagram' => ['nullable', 'url', 'max:200'], 'facebook' => ['nullable', 'url', 'max:200'], 'linkedin' => ['nullable', 'url', 'max:200'],
            'tab' => ['required', Rule::in(self::TABS)],
        ]);
        if ($request->has('popular')) {
            $words = array_values(array_filter(array_map('trim', explode(',', (string) $d['popular'])), fn ($w) => $w !== ''));
            SiteSettings::put('dryhire.popular', array_slice($words, 0, 12) ?: null, $request->user()->id);
        }
        if ($request->has('slide_seconds')) {
            SiteSettings::put('general.slide_seconds', (int) ($d['slide_seconds'] ?? 3), $request->user()->id);
            SiteSettings::put('general.social', array_filter(['instagram' => $d['instagram'] ?? null, 'facebook' => $d['facebook'] ?? null, 'linkedin' => $d['linkedin'] ?? null]) ?: null, $request->user()->id);
        }
        AuditLog::record('website.settings', null, ['page' => $d['tab']], $request->user());

        return $this->back($d['tab'], __('website.flash.saved'));
    }

    /** A new picture or video in a place. */
    public function store(Request $request): RedirectResponse
    {
        $this->can($request);
        $d = $this->validated($request, true);
        $file = $request->file('file');
        $video = in_array(strtolower($file->getClientOriginalExtension()), ['mp4', 'webm'], true) || str_starts_with((string) $file->getMimeType(), 'video/');
        $slide = Slide::create([
            'place' => $d['place'], 'position' => (int) Slide::where('place', $d['place'])->max('position') + 1, 'kind' => $video ? 'video' : 'image',
            'path' => $video ? $this->storeVideo($file) : Images::store($file, 'website', 2400),
            'poster_path' => $request->hasFile('poster') ? Images::store($request->file('poster'), 'website', 2400) : null,
            'mobile_path' => ! $video && $request->hasFile('mobile') ? Images::store($request->file('mobile'), 'website', 1600) : null,
            'updated_by_id' => $request->user()->id,
        ] + $this->fields($request, $d));
        AuditLog::record('website.slide_added', $slide, ['place' => $slide->place, 'kind' => $slide->kind, 'link' => $slide->link_type.' '.$slide->link_value], $request->user());
        $warn = $slide->kind === 'image' ? self::fitWarning($slide->path, $slide->place) : null;

        return $this->back($this->tabOf($slide), __('website.flash.added').($warn ? ' '.$warn : ''), 'slides');
    }

    public function update(Request $request, Slide $slide): RedirectResponse
    {
        $this->can($request);
        $d = $this->validated($request, false);
        $values = $this->fields($request, $d) + ['updated_by_id' => $request->user()->id];
        if ($request->hasFile('poster')) {
            $this->drop($slide->poster_path);
            $values['poster_path'] = Images::store($request->file('poster'), 'website', 2400);
        }
        if ($slide->kind === 'image' && $request->hasFile('mobile')) {
            $this->drop($slide->mobile_path);
            $values['mobile_path'] = Images::store($request->file('mobile'), 'website', 1600);
        } elseif ($request->boolean('remove_mobile') && $slide->mobile_path) {
            $this->drop($slide->mobile_path);
            $values['mobile_path'] = null;
        }
        $slide->update($values);
        AuditLog::record('website.slide_changed', $slide, ['place' => $slide->place, 'link' => $slide->link_type.' '.$slide->link_value, 'active' => $slide->active], $request->user());

        return $this->back($this->tabOf($slide), __('website.flash.saved'), 'slide-'.$slide->id);
    }

    public function move(Request $request, Slide $slide, string $dir): RedirectResponse
    {
        $this->can($request);
        $list = Slide::where('place', $slide->place)->orderBy('position')->orderBy('id')->get()->values();
        $i = $list->search(fn ($s) => $s->id === $slide->id);
        $j = $dir === 'up' ? $i - 1 : $i + 1;
        if ($j >= 0 && $j < $list->count()) {
            $other = $list[$j];
            $list[$j] = $slide;
            $list[$i] = $other;
        }
        foreach ($list as $n => $s) {
            $s->forceFill(['position' => $n])->save();
        }

        return $this->back($this->tabOf($slide), null, 'slides');
    }

    public function destroy(Request $request, Slide $slide): RedirectResponse
    {
        $this->can($request);
        AuditLog::record('website.slide_removed', null, ['place' => $slide->place, 'kind' => $slide->kind], $request->user());
        $this->drop($slide->path);
        $this->drop($slide->poster_path);
        $this->drop($slide->mobile_path);
        $tab = $this->tabOf($slide);
        $slide->delete();

        return $this->back($tab, __('website.flash.removed'), 'slides');
    }

    /** A template in the ideal size with the screen crops and the safe zone, to design on (SVG). */
    public function template(string $format): Response
    {
        abort_unless(isset(Slide::FORMATS[$format]), 404);
        $f = Slide::FORMATS[$format];
        [$sw, $sh] = Slide::safe($format);
        $frames = collect($f['frames'])->map(fn ($r, $name) => ['name' => __('website.format.screens.'.$name), 'crop' => Slide::crop($format, $r)]);
        $svg = view('media.website.template-svg', ['f' => $f, 'sw' => $sw, 'sh' => $sh, 'frames' => $frames, 'format' => $format])->render();

        return response($svg, 200, ['Content-Type' => 'image/svg+xml', 'Content-Disposition' => 'attachment; filename="cluk-'.$format.'-'.$f['w'].'x'.$f['h'].'.svg"']);
    }

    /** A gentle warning when a picture is much smaller or a very different shape than the ideal. */
    public static function fitWarning(?string $path, string $format): ?string
    {
        $full = $path ? Storage::disk('local')->path($path) : null;
        $size = $full && is_file($full) ? @getimagesize($full) : false;
        if (! $size || ! $size[1]) {
            return null;
        }
        $f = Slide::FORMATS[$format];
        $off = abs(($size[0] / $size[1]) / ($f['w'] / $f['h']) - 1);
        if ($size[0] < $f['w'] * 0.6 || $off > 0.2) {
            return __('website.format.warning', ['got' => $size[0].' × '.$size[1], 'ideal' => $f['w'].' × '.$f['h']]);
        }

        return null;
    }

    private function validated(Request $request, bool $new): array
    {
        $d = $request->validate([
            'place' => [$new ? 'required' : 'nullable', Rule::in(Slide::PLACES)],
            'file' => [$new ? 'required' : 'nullable', 'file', 'mimes:jpg,jpeg,png,webp,gif,mp4,webm', 'max:'.self::VIDEO_MAX_KB],
            'poster' => ['nullable', ...Images::rule()],
            'mobile' => ['nullable', ...Images::rule()],
            'remove_mobile' => ['boolean'],
            'link_type' => ['required', Rule::in(Slide::LINKS)],
            'link_value' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
            'starts_on' => ['nullable', 'date'], 'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'alt' => ['nullable', 'string', 'max:160'],
            'texts' => ['array'], 'texts.*' => ['array'], 'texts.*.*' => ['nullable', 'string', 'max:300'],
        ]);
        if ($new && ! in_array(strtolower($request->file('file')->getClientOriginalExtension()), ['mp4', 'webm'], true) && $request->file('file')->getSize() > 12288 * 1024) {
            throw ValidationException::withMessages(['file' => __('website.errors.image_too_big')]);
        }
        if (! SiteSettings::linkOk($d['link_type'], $d['link_value'] ?? null)) {
            throw ValidationException::withMessages(['link_value' => __('website.errors.link')]);
        }

        return $d;
    }

    private function fields(Request $request, array $d): array
    {
        $texts = [];
        foreach ($d['texts'] ?? [] as $loc => $t) {
            $t = array_filter(array_map(fn ($v) => trim((string) $v), $t), fn ($v) => $v !== '');
            if ($t && array_key_exists($loc, config('cluk.locales'))) {
                $texts[$loc] = $t;
            }
        }
        if ($texts && $request->boolean('translate') && Translator::available() && ! empty($texts['en'])) {
            $tr = app(Translator::class)->translate($texts['en']) ?? [];
            foreach ($tr as $loc => $t) {
                $texts[$loc] = ($texts[$loc] ?? []) + $t;
            }
        }

        return ['link_type' => $d['link_type'], 'link_value' => $d['link_type'] === 'none' ? null : ($d['link_value'] ?? null), 'texts' => $texts ?: null,
            'active' => $request->boolean('active', true), 'starts_on' => $d['starts_on'] ?? null, 'ends_on' => $d['ends_on'] ?? null, 'alt' => $d['alt'] ?? null];
    }

    /** With "fill the other languages": the English text is translated for the empty ones (Claude). */
    private function translate(Request $request, array $groups): array
    {
        if (! $request->boolean('translate') || ! Translator::available()) {
            return $groups;
        }
        $en = array_filter(array_map(fn ($g) => $g['en'] ?? null, $groups));
        $tr = $en ? (app(Translator::class)->translate($en) ?? []) : [];
        foreach ($tr as $loc => $fields) {
            foreach ($fields as $k => $v) {
                $groups[$k][$loc] ??= $v;
            }
        }

        return $groups;
    }

    private function storeVideo(UploadedFile $file): string
    {
        $ext = strtolower($file->getClientOriginalExtension()) === 'webm' ? 'webm' : 'mp4';

        return $file->storeAs('website', Str::random(32).'.'.$ext, 'local');
    }

    private function drop(?string $path): void
    {
        if ($path && str_starts_with($path, 'website/')) {
            Storage::disk('local')->delete($path);
        }
    }

    private function can(Request $request): void
    {
        abort_unless($request->user()->hasRight('website.edit'), 403);
    }

    private function tabOf(Slide $s): string
    {
        return (string) array_search($s->place, self::PLACE_OF, true) ?: 'home';
    }

    private function back(string $tab, ?string $msg, ?string $anchor = null): RedirectResponse
    {
        $r = redirect()->to(route('website.index', ['tab' => $tab]).($anchor ? '#'.$anchor : ''));

        return $msg ? $r->with('status', $msg) : $r;
    }
}
