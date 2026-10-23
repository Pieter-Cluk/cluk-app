<?php

namespace App\Http\Controllers\Site;

use App\Models\Equipment\Item;
use App\Services\Website\DryHireCatalogue;
use App\Services\Website\WebRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** The dry-hire catalogue on the website: everything the platform has, searchable. */
class DryHireController extends SiteController
{
    public function index(Request $request): View
    {
        $locale = $this->locale($request);

        $cust = \App\Services\Web\CustomerAuth::current();
        $cfg = [
            'customer' => $cust ? ['name' => $cust->name(), 'first' => $cust->first_name] : null,
            'acct' => [
                'login' => route('site.account.login'), 'set' => route('site.account.basket.set', 'dryhire'), 'merge' => route('site.account.basket.merge', 'dryhire'),
                'dates' => route('site.account.basket.dates'), 'send' => route('site.account.basket.send'),
            ],
            't' => array_merge(__('site.js'), __('site.ajs'), [
                'type' => __('site.dryhire.type'), 'tags' => __('site.dryhire.tags'), 'all' => __('site.dryhire.all'),
                'show_all' => __('site.dryhire.show_all_brands'), 'show_fewer' => __('site.dryhire.show_fewer'),
            ]),
            'locale' => $locale,
            'urls' => [
                'data' => str_replace('987654321', '__ID__', route('site.dryhire.data', ['item' => 987654321])),
                'photo' => str_replace('987654321', '__ID__', route('site.photo', ['item' => 987654321, 'w' => 480])).'?v=__V__',
                'item' => str_replace('987654321-x', '__SL__', route('site.dryhire.item', ['item' => '987654321-x'])),
                'request' => route('site.dryhire.request'),
            ],
        ];

        return view('site.dryhire', ['data' => DryHireCatalogue::data($locale), 'locale' => $locale, 'cfg' => $cfg]);
    }

    /** Product page (/dry-hire/123-l-acoustics-k2): for search engines and shared links. */
    public function show(Request $request, string $item): mixed
    {
        $locale = $this->locale($request);
        $model = $this->find($item);
        if (DryHireCatalogue::slug($model) !== $item) {
            return redirect()->route('site.dryhire.item', DryHireCatalogue::slug($model), 301);
        }

        return view('site.dryhire-item', ['d' => DryHireCatalogue::detail($model, $locale), 'item' => $model, 'locale' => $locale]);
    }

    /** The same details for the product panel in the catalogue. */
    public function data(Request $request, int $item): JsonResponse
    {
        $locale = $this->locale($request);

        return response()->json(DryHireCatalogue::detail($this->find((string) $item), $locale))->header('Cache-Control', 'public, max-age=300');
    }

    public function photo(Request $request, int $item, int $w): BinaryFileResponse
    {
        abort_unless(in_array($w, config('website.photo_widths'), true), 404);
        $model = $this->find((string) $item);
        $file = DryHireCatalogue::photoFile($model, $w, $request->integer('p') ?: null);
        abort_unless($file, 404);

        return response()->file($file, ['Cache-Control' => 'public, max-age=604800']);
    }

    public function request(Request $request): JsonResponse
    {
        $locale = $this->locale($request);
        if (filled($request->input('website_url'))) { // the hidden field only bots fill in
            return response()->json(['ok' => true]);
        }
        $d = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'email' => ['required', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:40'],
            'company' => ['nullable', 'string', 'max:160'],
            'vat' => ['nullable', 'string', 'max:30'],
            'message' => ['nullable', 'string', 'max:5000'],
            'first_day' => ['required', 'date', 'after_or_equal:today'],
            'last_day' => ['required', 'date', 'after_or_equal:first_day'],
            'lines' => ['array', 'max:'.config('website.max_lines')],
            'lines.*.id' => ['required', 'integer'],
            'lines.*.qty' => ['required', 'integer', 'min:1', 'max:'.config('website.max_qty')],
            'page' => ['nullable', 'string', 'max:300'],
        ]);
        abort_if(empty($d['lines']) && blank($d['message'] ?? null), 422, __('site.js.need_lines'));

        $msg = WebRequests::dryHire($d, $locale);
        \App\Services\Website\Tracking::linkRequest($request, $msg);

        return response()->json(['ok' => true, 'ref' => 'W-'.$msg->id]);
    }

    private function find(string $slug): Item
    {
        $id = (int) Str::before($slug, '-');
        $item = Item::find($id);
        abort_unless($item && DryHireCatalogue::visible($item), 404);

        return $item;
    }
}
