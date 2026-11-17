<?php

namespace App\Http\Controllers\Warehouse;

use App\Http\Controllers\Controller;
use App\Models\CompanyDetail;
use App\Models\Warehouse\Charge;
use App\Models\Warehouse\ChargeNotice;
use App\Models\Warehouse\Check;
use App\Services\Warehouse\CostNotices;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

/** The customer's page for return costs: the lines with the photos, and a way to react before the deadline. */
class CostsPublicController extends Controller
{
    private function find(Request $request, string $token): ChargeNotice
    {
        $n = ChargeNotice::with('order.company', 'order.contact', 'sentBy')->where('token', $token)->firstOrFail();
        $lang = $request->query('lang');
        app()->setLocale(array_key_exists((string) $lang, config('cluk.locales')) ? $lang : (array_key_exists($n->order->language, config('cluk.locales')) ? $n->order->language : 'en'));

        return $n;
    }

    public function show(Request $request, string $token): View
    {
        $n = $this->find($request, $token);
        $charges = Charge::with('check')->where('order_id', $n->order_id)->whereIn('status', ['informed', 'pending', 'approved'])->orderBy('id')->get();

        return view('warehouse.costs-public', ['n' => $n, 'order' => $n->order, 'charges' => $charges, 'me' => CompanyDetail::current(),
            'vatBp' => (int) ($n->order->vat_rate_bp ?? 0)]);
    }

    /** A photo of a check that led to a cost on this page. */
    public function photo(Request $request, string $token, Check $check, int $i)
    {
        $n = $this->find($request, $token);
        abort_unless(Charge::where('order_id', $n->order_id)->where('check_id', $check->id)->exists(), 404);
        $path = $check->photos[$i]['path'] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, max-age=86400']);
    }

    public function react(Request $request, string $token): RedirectResponse
    {
        $n = $this->find($request, $token);
        if ($n->status !== 'sent') {
            return back();
        }
        CostNotices::object($n, $request->validate(['text' => ['required', 'string', 'min:5', 'max:2000']])['text']);

        return back()->with('status', __('checks.public.thanks'));
    }
}
