<?php

namespace App\Http\Controllers;

use App\Models\CompanyDetail;
use App\Models\Sales\Quote;
use App\Services\Rental\QuoteAnswer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/** The customer's page behind the link in the quote email: look, accept, ask a change, decline. */
class QuotePublicController extends Controller
{
    private function find(string $token): Quote
    {
        $quote = Quote::with(['lines', 'company', 'contact', 'owner'])->where('token', $token)->firstOrFail();
        abort_if($quote->status === 'draft', 404);

        return $quote;
    }

    private function locale(Request $request, Quote $quote): void
    {
        $lang = $request->query('lang', session('quote_lang', $quote->language));
        if (array_key_exists($lang, config('cluk.locales'))) {
            session(['quote_lang' => $lang]);
            app()->setLocale($lang);
        }
    }

    public function show(Request $request, string $token): View
    {
        $quote = $this->find($token);
        $this->locale($request, $quote);
        $newer = Quote::where('number', $quote->number)->where('version', '>', $quote->version)->whereNotNull('token')->where('status', '!=', 'draft')->orderByDesc('version')->first();

        return view('sales.quotes.public', ['quote' => $quote, 'me' => CompanyDetail::current(), 'newer' => $newer]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $quote = $this->find($token);
        $this->locale($request, $quote);
        abort_unless($quote->isOpenForCustomer(), 409);
        $d = $request->validate(['name' => ['required', 'string', 'max:120'], 'po_number' => ['nullable', 'string', 'max:80'], 'terms' => ['accepted']]);
        QuoteAnswer::accept($quote, $d['name'], $d['po_number'] ?? null, $request->ip());

        return redirect()->route('quote.public', $token)->with('answered', 'accepted');
    }

    public function change(Request $request, string $token): RedirectResponse
    {
        $quote = $this->find($token);
        $this->locale($request, $quote);
        abort_unless($quote->isOpenForCustomer(), 409);
        $d = $request->validate(['message' => ['required', 'string', 'max:3000'], 'name' => ['nullable', 'string', 'max:120']]);
        QuoteAnswer::askChange($quote, (string) ($d['name'] ?? ''), $d['message'], $request->ip());

        return redirect()->route('quote.public', $token)->with('answered', 'change');
    }

    public function decline(Request $request, string $token): RedirectResponse
    {
        $quote = $this->find($token);
        $this->locale($request, $quote);
        abort_unless($quote->isOpenForCustomer(), 409);
        $d = $request->validate(['reason' => ['required', Rule::in(Quote::DECLINE_REASONS)], 'note' => ['nullable', 'string', 'max:1000']]);
        QuoteAnswer::decline($quote, $d['reason'], $d['note'] ?? null, $request->ip());

        return redirect()->route('quote.public', $token)->with('answered', 'declined');
    }

    /** The order confirmation, once asset management approved the order. */
    public function order(Request $request, string $token): View
    {
        $quote = $this->find($token);
        $order = $quote->order;
        abort_unless($order && $order->status === 'approved', 404);
        $this->locale($request, $quote);

        return view('rental.orders.print', ['order' => $order->load(['lines', 'company', 'contact', 'handedBy']), 'me' => CompanyDetail::current()]);
    }

    public function print(Request $request, string $token): View
    {
        $quote = $this->find($token);
        $this->locale($request, $quote);

        return view('sales.quotes.print', ['quote' => $quote, 'me' => CompanyDetail::current()]);
    }
}
