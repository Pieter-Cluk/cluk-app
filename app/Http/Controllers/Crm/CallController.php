<?php

namespace App\Http\Controllers\Crm;

use App\Http\Controllers\Controller;
use App\Models\Company;
use App\Models\Contact;
use App\Support\Phone;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Call pop-up: Linkus opens /call?number=…&name=… when a call rings or is answered.
 * One match opens that card; several show a short list; an unknown number offers to
 * add it. Works however the number was typed in the app (international keys).
 */
class CallController extends Controller
{
    public function show(Request $request): View|RedirectResponse
    {
        $raw = trim((string) $request->query('number', ''));
        $name = trim((string) $request->query('name', ''));
        $key = Phone::key($raw);
        [$contacts, $companies] = $key ? self::find($key) : [collect(), collect()];
        $shown = Phone::international($raw) ?? $raw;

        if ($contacts->count() + $companies->count() === 1) {
            $msg = __('calls.from', ['number' => $shown]);
            if ($c = $contacts->first()) {
                $at = $c->companies->sortByDesc('pivot.is_main')->first();

                return $at
                    ? redirect()->to(route('companies.show', $at).'#contact-'.$c->id)->with('status', $msg.' · '.$c->name)
                    : redirect()->route('companies.index', ['view' => 'contacts', 'q' => $c->email ?: $c->name])->with('status', $msg.' · '.$c->name);
            }

            return redirect()->route('companies.show', $companies->first())->with('status', $msg);
        }

        return view('calls.show', ['raw' => $raw, 'shown' => $shown, 'name' => $name, 'key' => $key, 'contacts' => $contacts, 'companies' => $companies]);
    }

    /** Contacts and companies with this number (exact key; else the last 9 digits, for numbers typed oddly). */
    public static function find(string $key): array
    {
        $tail = substr($key, -9);
        $contactQ = fn (Builder $q) => $q->where('phone_key', $key)->orWhere('mobile_key', $key);
        $contacts = Contact::with('companies')->where($contactQ)->limit(20)->get();
        $companies = Company::where('phone_key', $key)->limit(20)->get();
        if ($contacts->isEmpty() && $companies->isEmpty() && strlen($tail) === 9) {
            $contacts = Contact::with('companies')->where(fn ($q) => $q->where('phone_key', 'like', '%'.$tail)->orWhere('mobile_key', 'like', '%'.$tail))->limit(20)->get();
            $companies = Company::where('phone_key', 'like', '%'.$tail)->limit(20)->get();
        }
        // A company already reached through its contact is not listed twice.
        $viaContacts = $contacts->flatMap(fn ($c) => $c->companies->pluck('id'));

        return [$contacts, $companies->reject(fn ($co) => $contacts->isNotEmpty() && $viaContacts->contains($co->id) && $co->phone_key !== $key)->values()];
    }
}
