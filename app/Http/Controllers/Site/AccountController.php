<?php

namespace App\Http\Controllers\Site;

use App\Models\Web\CustomerAccount;
use App\Services\Web\Baskets;
use App\Services\Web\CustomerAuth;
use App\Services\Website\Tracking;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Customer accounts on the website: log in, register, password, My CLUK, and the
 * two baskets kept on the server (dry-hire request and sales order on invoice).
 * The back end is App\Services\Web\CustomerAuth and App\Services\Web\Baskets.
 */
class AccountController extends SiteController
{
    public function login(Request $request): View|RedirectResponse
    {
        $this->locale($request);
        if (CustomerAuth::current()) {
            return redirect()->route('site.account');
        }

        return view('site.account.login', ['notice' => $request->boolean('verified') ? __('web.api.verified') : session('notice')]);
    }

    public function doLogin(Request $request): RedirectResponse
    {
        $this->locale($request);
        $d = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'remember' => ['nullable']]);
        $why = CustomerAuth::attempt($d['email'], $d['password'], $request->boolean('remember'));
        if ($why) {
            return back()->withInput($request->only('email'))->withErrors(['email' => __('web.api.login_'.$why)]);
        }
        Tracking::linkAccount($request, CustomerAuth::current());

        return redirect()->intended(route('site.account'));
    }

    public function logout(Request $request): RedirectResponse
    {
        CustomerAuth::logout();

        return redirect()->route('site.home');
    }

    public function register(Request $request): View|RedirectResponse
    {
        $this->locale($request);

        return CustomerAuth::current() ? redirect()->route('site.account') : view('site.account.register');
    }

    public function doRegister(Request $request): RedirectResponse
    {
        $locale = $this->locale($request);
        if (filled($request->input('website_url'))) {
            return redirect()->route('site.account.login')->with('notice', __('web.api.registered'));
        }
        $d = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'],
            'company_name' => ['nullable', 'string', 'max:160'], 'vat_number' => ['nullable', 'string', 'max:30'], 'phone' => ['nullable', 'string', 'max:40'],
            'email' => ['required', 'email', 'max:190', Rule::unique('customer_accounts', 'email')],
            'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed'],
            'terms' => ['accepted'],
        ]);
        Tracking::linkAccount($request, CustomerAuth::register($d + ['locale' => $locale]), 'register');

        return redirect()->route('site.account.login')->with('notice', __('web.api.registered'));
    }

    public function forgot(Request $request): View
    {
        $this->locale($request);

        return view('site.account.forgot', ['notice' => session('notice')]);
    }

    public function doForgot(Request $request): RedirectResponse
    {
        $this->locale($request);
        $d = $request->validate(['email' => ['required', 'email']]);
        if ($a = CustomerAccount::where('email', strtolower(trim($d['email'])))->first()) {
            CustomerAuth::sendReset($a);
        }

        return back()->with('notice', __('web.api.reset_sent'));
    }

    public function reset(Request $request): View
    {
        $this->locale($request);

        return view('site.account.reset', ['email' => (string) $request->query('email'), 'token' => (string) $request->query('token')]);
    }

    public function doReset(Request $request): RedirectResponse
    {
        $this->locale($request);
        $d = $request->validate(['email' => ['required', 'email'], 'token' => ['required', 'string'], 'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed']]);
        if (! CustomerAuth::reset($d['email'], $d['token'], $d['password'])) {
            return back()->withErrors(['password' => __('web.api.reset_invalid')]);
        }

        return redirect()->route('site.account.login')->with('notice', __('web.api.reset_done'));
    }

    /** My CLUK: details, password, baskets and history. */
    public function show(Request $request): View
    {
        $this->locale($request);
        $a = CustomerAuth::current();

        return view('site.account.show', [
            'a' => $a->loadMissing('company'), 'history' => Baskets::history($a),
            'dry' => Baskets::show($a, 'dryhire'), 'sales' => Baskets::show($a, 'sales'), 'notice' => session('notice'),
        ]);
    }

    public function profile(Request $request): RedirectResponse
    {
        $this->locale($request);
        $d = $request->validate([
            'first_name' => ['required', 'string', 'max:80'], 'last_name' => ['required', 'string', 'max:80'], 'company_name' => ['nullable', 'string', 'max:160'],
            'vat_number' => ['nullable', 'string', 'max:30'], 'phone' => ['nullable', 'string', 'max:40'], 'locale' => ['required', Rule::in(array_keys(config('cluk.locales')))],
        ]);
        CustomerAuth::saveProfile(CustomerAuth::current(), $d);

        return redirect()->to(route('site.account').'?lang='.$d['locale'])->with('notice', __('site.account.saved'));
    }

    public function password(Request $request): RedirectResponse
    {
        $this->locale($request);
        $d = $request->validate(['current' => ['required', 'string'], 'password' => ['required', 'string', 'min:10', 'max:200', 'confirmed']]);
        if (! CustomerAuth::changePassword(CustomerAuth::current(), $d['current'], $d['password'])) {
            return back()->withErrors(['current' => __('site.account.password_wrong')]);
        }

        return back()->with('notice', __('site.account.password_saved'));
    }

    // ---------- baskets (JSON, signed-in customers) ----------

    public function basket(Request $request, string $kind): JsonResponse
    {
        $this->locale($request);

        return response()->json(Baskets::show(CustomerAuth::current(), $kind));
    }

    public function basketSet(Request $request, string $kind): JsonResponse
    {
        $this->locale($request);
        $d = $request->validate(['id' => ['required', 'integer'], 'qty' => ['required', 'integer', 'min:0', 'max:'.config('website.max_qty')]]);
        $a = CustomerAuth::current();
        $err = Baskets::set($a, $kind, $d['id'], $d['qty']);

        return response()->json(Baskets::show($a, $kind) + ['error' => $err], $err ? 422 : 200);
    }

    public function basketMerge(Request $request, string $kind): JsonResponse
    {
        $this->locale($request);
        $d = $request->validate(['lines' => ['array', 'max:'.config('website.max_lines')], 'lines.*.id' => ['required', 'integer'], 'lines.*.qty' => ['required', 'integer', 'min:1', 'max:'.config('website.max_qty')],
            'first_day' => ['nullable', 'date'], 'last_day' => ['nullable', 'date']]);
        $a = CustomerAuth::current();
        Baskets::merge($a, $kind, $d['lines'] ?? []);
        if ($kind === 'dryhire' && ! empty($d['first_day']) && ! empty($d['last_day']) && $d['first_day'] >= now()->toDateString() && $d['last_day'] >= $d['first_day']) {
            $now = Baskets::show($a, $kind);
            if (! $now['first_day']) {
                Baskets::dates($a, $d['first_day'], $d['last_day']);
            }
        }

        return response()->json(Baskets::show($a, $kind));
    }

    public function basketDates(Request $request): JsonResponse
    {
        $this->locale($request);
        $d = $request->validate(['first_day' => ['required', 'date', 'after_or_equal:today'], 'last_day' => ['required', 'date', 'after_or_equal:first_day']]);
        $a = CustomerAuth::current();
        Baskets::dates($a, $d['first_day'], $d['last_day']);

        return response()->json(Baskets::show($a, 'dryhire'));
    }

    public function sendDryHire(Request $request): JsonResponse
    {
        $this->locale($request);
        $d = $request->validate(['message' => ['nullable', 'string', 'max:5000'], 'page' => ['nullable', 'string', 'max:300']]);
        $msg = Baskets::submitDryHire(CustomerAuth::current(), $d);
        Tracking::linkRequest($request, $msg);

        return response()->json(['ok' => true, 'ref' => 'W-'.$msg->id]);
    }

    public function orderSales(Request $request): JsonResponse
    {
        $this->locale($request);
        $d = $request->validate(['po_number' => ['nullable', 'string', 'max:60'], 'message' => ['nullable', 'string', 'max:5000']]);
        $q = Baskets::submitSales(CustomerAuth::current(), $d);
        Tracking::record($request, 'order', ['section' => 'sales', 'label' => $q->number, 'meta' => ['sales_quote_id' => $q->id, 'net_cents' => (int) $q->net_cents]]);

        return response()->json(['ok' => true, 'number' => $q->number]);
    }
}
