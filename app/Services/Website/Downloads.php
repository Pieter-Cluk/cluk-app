<?php

namespace App\Services\Website;

use App\Models\Contact;
use App\Models\Website\Download;
use App\Models\Website\DownloadLog;
use App\Services\Web\CustomerAuth;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Who downloads what: a signed-in customer is known by their login; otherwise the
 * name and email they gave (kept for the visit, so a second download does not ask
 * again), matched to a contact and company by email; otherwise the visitor's
 * earlier requests (only with statistics cookies). The download also counts as a
 * website signal for the commercial tasks of the account owner.
 */
class Downloads
{
    public const SESSION = 'dl_who';

    /** Who this visitor is, as far as we know: ['account' => ?, 'name', 'email', 'company'] or null. */
    public static function who(Request $r): ?array
    {
        if ($a = CustomerAuth::current()) {
            return ['account' => $a, 'name' => $a->name(), 'email' => $a->email, 'company' => $a->company?->name ?? $a->company_name];
        }

        return $r->session()->get(self::SESSION);
    }

    public static function mayGet(Download $d, Request $r): bool
    {
        return match ($d->access) {
            'login' => (bool) CustomerAuth::current(),
            'email' => (bool) self::who($r),
            default => true,
        };
    }

    public static function log(Download $d, Request $r): DownloadLog
    {
        $who = self::who($r) ?? [];
        $account = $who['account'] ?? null;
        $visitor = Tracking::visitor($r);
        $email = $who['email'] ?? $visitor?->email ?? null;
        $contact = $account?->contact_id ? Contact::find($account->contact_id) : ($email ? Contact::whereRaw('lower(email) = ?', [Str::lower($email)])->first() : null);
        $company = $account?->company_id ?? $contact?->companies()->orderByDesc('company_contact.is_main')->value('companies.id') ?? $visitor?->company_id;

        $log = DownloadLog::create([
            'download_id' => $d->id, 'customer_account_id' => $account?->id, 'contact_id' => $contact?->id ?? $visitor?->contact_id, 'company_id' => $company,
            'visitor_id' => $visitor?->id, 'name' => isset($who['name']) ? Str::limit($who['name'], 118, '') : null, 'email' => $email ? Str::limit($email, 188, '') : null,
            'company_name' => isset($who['company']) ? Str::limit((string) $who['company'], 188, '') : null, 'locale' => app()->getLocale(), 'created_at' => now(),
        ]);
        DB::table('web_downloads')->where('id', $d->id)->increment('downloads');
        // A website signal (only with statistics cookies), linked to who they are.
        if ($visitor && ($email || $company)) {
            DB::table('web_visitors')->where('id', $visitor->id)->update(array_filter(['email' => $email, 'contact_id' => $log->contact_id, 'company_id' => $company, 'updated_at' => now()]));
        }
        Tracking::record($r, 'download', ['section' => 'downloads', 'label' => $d->label('en', 'title'), 'meta' => ['download_id' => $d->id]]);

        return $log;
    }
}
