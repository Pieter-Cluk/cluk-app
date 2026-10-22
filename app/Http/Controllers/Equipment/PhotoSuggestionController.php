<?php

namespace App\Http\Controllers\Equipment;

use App\Http\Controllers\Controller;
use App\Models\Equipment\PhotoSuggestion;
use App\Services\Equipment\PhotoSuggestions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** Dry-hire › Photos found on the web: pick the right one per product, or none. */
class PhotoSuggestionController extends Controller
{
    public function index(Request $request): View
    {
        $status = in_array($request->query('s'), PhotoSuggestion::STATUSES, true) ? $request->query('s') : 'pending';

        return view('equipment.photo-suggestions', [
            'status' => $status,
            'rows' => PhotoSuggestion::where('status', $status)->orderBy('title')->paginate(24)->withQueryString(),
            'counts' => collect([...PhotoSuggestion::STATUSES, 'new'])->mapWithKeys(fn ($s) => [$s => PhotoSuggestion::where('status', $s)->count()]),
        ]);
    }

    public function image(PhotoSuggestion $suggestion, int $i)
    {
        $f = $suggestion->usable()[$i] ?? null;
        abort_unless($f && Storage::disk('local')->exists($f), 404);

        return response()->file(Storage::disk('local')->path($f), ['Cache-Control' => 'private, max-age=86400']);
    }

    public function decide(Request $request, PhotoSuggestion $suggestion): RedirectResponse
    {
        abort_unless($suggestion->status === 'pending', 409);
        $d = $request->validate(['use' => ['nullable', 'integer', 'min:0', 'max:9']]);
        if (isset($d['use'])) {
            $n = PhotoSuggestions::approve($suggestion, (int) $d['use'], $request->user());

            return back()->with('status', trans_choice('equipment.photos_found.approved', $n, ['n' => $n, 'title' => $suggestion->title]));
        }
        PhotoSuggestions::reject($suggestion, $request->user());

        return back()->with('status', __('equipment.photos_found.rejected', ['title' => $suggestion->title]));
    }

    /** "The first photo is right for all of these": approve the first usable photo of the products on this page. */
    public function bulk(Request $request): RedirectResponse
    {
        $ids = $request->validate(['ids' => ['required', 'array', 'max:50'], 'ids.*' => ['integer']])['ids'];
        $done = 0;
        foreach (PhotoSuggestion::whereIn('id', $ids)->where('status', 'pending')->get() as $s) {
            $first = array_key_first($s->usable());
            if ($first !== null) {
                PhotoSuggestions::approve($s, $first, $request->user());
                $done++;
            }
        }

        return back()->with('status', trans_choice('equipment.photos_found.bulk_done', $done, ['n' => $done]));
    }
}
