<?php

namespace App\Http\Controllers\Site;

use App\Models\Website\Download;
use App\Services\Website\Tracking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Footer › Downloads: documents customers can download (managed in the platform). */
class DownloadController extends SiteController
{
    public function index(Request $request): View
    {
        $locale = $this->locale($request);
        $docs = Download::where('active', true)->orderBy('sort')->orderBy('id')->get()
            ->sortBy(fn ($d) => $d->locale === $locale || $d->locale === null ? 0 : 1, SORT_REGULAR)->values();
        $groups = collect(config('website.download_categories'))->mapWithKeys(fn ($c) => [$c => $docs->where('category', $c)->values()])
            ->put('other', $docs->filter(fn ($d) => $d->category === 'other' || ! in_array($d->category, config('website.download_categories'), true))->values());

        // The general terms and conditions in every language are always on the page.
        $terms = collect(config('rental.terms_files'))->filter(fn ($f) => is_file(public_path($f)))
            ->map(fn ($f, $l) => ['locale' => $l, 'url' => route('terms', $l), 'size' => filesize(public_path($f))])
            ->sortBy(fn ($t) => $t['locale'] === $locale ? 0 : 1);

        return view('site.downloads', ['groups' => $groups, 'terms' => $terms, 'locale' => $locale]);
    }

    public function file(Request $request, int $download): BinaryFileResponse
    {
        $d = Download::where('active', true)->findOrFail($download);
        abort_unless(Storage::disk('local')->exists($d->path), 404);
        $d->increment('downloads');
        Tracking::record($request, 'download', ['section' => 'downloads', 'label' => $d->label('en', 'title'), 'meta' => ['download_id' => $d->id]]);
        $name = Str::ascii($d->original_name ?: basename($d->path));
        $inline = in_array($d->mime, ['application/pdf', 'image/jpeg', 'image/png'], true);

        return response()->file(Storage::disk('local')->path($d->path), array_filter([
            'Content-Type' => $d->mime, 'Content-Disposition' => ($inline ? 'inline' : 'attachment').'; filename="'.str_replace('"', '', $name).'"', 'Cache-Control' => 'public, max-age=3600',
        ]));
    }
}
