<?php

namespace App\Http\Controllers\ProductSales;

use App\Http\Controllers\Controller;
use App\Models\CompanyDetail;
use App\Models\ProductSales\SalesQuote;
use App\Services\ProductSales\SalesFlow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/** The customer's link: read the sales quote, accept it with a PO number, or decline. */
class SalesPublicController extends Controller
{
    private function find(string $token): SalesQuote
    {
        $q = SalesQuote::with(['lines', 'company', 'contact', 'owner'])->where('token', $token)->firstOrFail();
        app()->setLocale(array_key_exists($q->language, config('cluk.locales')) ? $q->language : 'en');

        return $q;
    }

    public function show(string $token): View
    {
        $q = $this->find($token);

        return view('psales.doc', ['q' => $q, 'type' => in_array($q->status, ['confirmed', 'delivered'], true) ? 'oc' : 'quo', 'code' => $q->code(), 'me' => CompanyDetail::current(), 'prices' => true, 'public' => true]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $q = $this->find($token);
        abort_unless($q->status === 'sent' && (! $q->valid_until || $q->valid_until->gte(today())), 409);
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'po_number' => ['nullable', 'string', 'max:80'], 'terms' => ['accepted']]);
        SalesFlow::accept($q, $d['name'], $d['po_number'] ?? null);

        return back()->with('status', __('psales.public.thanks'));
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        $q = $this->find($token);
        abort_unless($q->status === 'sent', 409);
        SalesFlow::decline($q, $request->validate(['reason' => ['nullable', 'string', 'max:500']])['reason'] ?? null);

        return back()->with('status', __('psales.public.declined'));
    }
}
