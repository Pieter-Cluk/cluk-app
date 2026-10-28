<?php

namespace App\Services\Web;

use App\Models\Company;
use App\Models\Contact;
use App\Models\Web\CustomerAccount;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Companies › Website logins: the checks for a new registration, done in advance. It proposes the company and
 * the contact; when everything matches ("ready"), confirming is one click (or all ready ones at once).
 *
 * Ready = email address confirmed + a company found + (the same VAT number, or the person is already a contact
 * of that company) + nothing that contradicts it.
 */
class ApprovalCheck
{
    /** Free mail providers: the domain says nothing about the company. */
    private const FREE = '/^(gmail|googlemail|hotmail|outlook|live|msn|yahoo|ymail|icloud|me|mac|aol|gmx|web|proton|protonmail|telenet|skynet|proximus|scarlet|pandora|orange|free|sfr|wanadoo|laposte|ziggo|kpnmail|kpnplanet|planet|home|hetnet|xs4all|t-online|freenet|libero|virgilio|tiscali|bluewin|zoho|mail|yandex)\./i';

    /**
     * @return array{company: ?Company, contact: ?Contact, choice: string, companies: Collection, checks: array<int, array{0: string, 1: string, 2: array}>, ready: bool}
     */
    public static function for(CustomerAccount $a): array
    {
        $email = mb_strtolower(trim($a->email));
        $domain = (string) Str::after($email, '@');
        $free = (bool) preg_match(self::FREE, $domain);
        $checks = [];

        $contact = Contact::with('companies')->whereRaw('lower(email) = ?', [$email])->first();
        $vat = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', (string) $a->vat_number));
        $vatCompanies = $vat === '' ? collect() : Company::whereIn('vat_number', array_unique([$vat, ctype_digit($vat) ? 'BE'.str_pad($vat, 10, '0', STR_PAD_LEFT) : $vat]))->get();
        $contactCompanies = $contact?->companies ?? collect();
        $domainCompanies = $free || $domain === '' ? collect() : Company::where(fn ($q) => $q->whereRaw('lower(email) like ?', ['%@'.$domain])->orWhereRaw('lower(website) like ?', ['%'.$domain.'%'])
            ->orWhereIn('id', DB::table('company_contact')->join('contacts', 'contacts.id', '=', 'company_contact.contact_id')->whereRaw('lower(contacts.email) like ?', ['%@'.$domain])->select('company_contact.company_id')))
            ->limit(5)->get();

        // The best company: VAT and contact agree > VAT > the person's company > the mail domain > a similar name.
        $both = $vatCompanies->whereIn('id', $contactCompanies->pluck('id'));
        $company = $both->first()
            ?? ($vatCompanies->count() === 1 ? $vatCompanies->first() : null)
            ?? ($contactCompanies->count() === 1 ? $contactCompanies->first() : null)
            ?? ($domainCompanies->count() === 1 ? $domainCompanies->first() : null);
        $nameCompanies = ! $company && $a->company_name ? Company::where('name', 'like', '%'.Str::limit($a->company_name, 40, '').'%')->limit(5)->get() : collect();
        $companies = $vatCompanies->merge($contactCompanies)->merge($domainCompanies)->merge($nameCompanies)->unique('id')->values();

        $checks[] = $a->email_verified_at ? ['ok', 'email_verified', []] : ['warn', 'email_not_verified', []];
        if ($contact) {
            $checks[] = ['ok', 'known_contact', ['name' => trim($contact->first_name.' '.$contact->last_name), 'company' => $contactCompanies->pluck('name')->implode(', ') ?: '—']];
        } else {
            $checks[] = ['info', 'new_contact', []];
        }
        if ($vat === '') {
            $checks[] = ['info', 'no_vat', []];
        } elseif ($vatCompanies->isNotEmpty()) {
            $checks[] = ['ok', 'vat_match', ['company' => $vatCompanies->pluck('name')->implode(', ')]];
        } else {
            $checks[] = ['warn', 'vat_no_match', ['vat' => $a->vat_number]];
        }
        $conflict = $vatCompanies->isNotEmpty() && $contactCompanies->isNotEmpty() && $both->isEmpty();
        if ($conflict) {
            $checks[] = ['warn', 'conflict', ['vat' => $vatCompanies->first()->name, 'contact' => $contactCompanies->first()->name]];
        }
        if ($free) {
            $checks[] = ['info', 'free_mail', ['domain' => $domain]];
        } elseif ($company && $domainCompanies->contains('id', $company->id)) {
            $checks[] = ['ok', 'domain', ['domain' => $domain]];
        }
        $invited = DB::table('message_sends')->join('messages', 'messages.id', '=', 'message_sends.message_id')
            ->where('messages.kind', 'invitation')->where('message_sends.email', $email)->where('message_sends.status', 'sent')->max('message_sends.sent_at');
        if ($invited) {
            $checks[] = ['ok', 'invited', ['date' => \Illuminate\Support\Carbon::parse($invited)->format('d/m/Y')]];
        }
        if ($company && ! $company->is_customer) {
            $checks[] = ['info', 'not_customer', ['company' => $company->name]];
        }
        if (! $company) {
            $checks[] = ['warn', 'no_company', []];
        }

        $ready = $a->email_verified_at && $company && ! $conflict
            && ($vatCompanies->contains('id', $company->id) || $contactCompanies->contains('id', $company->id))
            && ! ($vat !== '' && $vatCompanies->isEmpty());

        return [
            'company' => $company, 'contact' => $contact, 'companies' => $companies, 'checks' => $checks, 'ready' => (bool) $ready,
            'choice' => $company ? (string) $company->id : ($a->company_name ? 'new' : 'none'),
        ];
    }
}
