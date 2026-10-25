<?php

namespace App\Services\Finance\OkiOki;

use App\Models\AuditLog;
use App\Models\CompanyDetail;
use App\Models\Finance\PreInvoice;
use App\Models\User;
use App\Notifications\Confirmation;
use App\Support\SafeMail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * The OkiOki link. OkiOki has no API for sales invoices, but it takes e-invoices
 * (Peppol UBL) mailed to CLUK's own OkiOki address, books them and sends them to
 * the customer over Peppol (OkiOki Smart, Peppol verified, automatic sending on).
 * The app gives the invoice number from its own series.
 */
class OkiOki
{
    public static function on(): bool
    {
        $me = CompanyDetail::current();

        return (bool) $me->okioki_send && filled($me->okioki_inbox);
    }

    /** The next number of the series, and the series moves on (one number per invoice, no gaps). */
    public static function nextNumber(): string
    {
        return DB::transaction(function () {
            $me = CompanyDetail::query()->orderBy('id')->lockForUpdate()->first();
            $year = (int) now()->year;
            $prefix = (string) ($me->invoice_prefix ?? '{year}-');
            $next = (int) $me->invoice_next;
            if (str_contains($prefix, '{year}') && $me->invoice_year && (int) $me->invoice_year !== $year) {
                $next = 1; // a new year starts a new series
            }
            $number = str_replace('{year}', (string) $year, $prefix).str_pad((string) $next, 4, '0', STR_PAD_LEFT);
            $me->forceFill(['invoice_next' => $next + 1, 'invoice_year' => $year])->save();

            return $number;
        });
    }

    /** What the next number will look like, for the screen. */
    public static function preview(): string
    {
        $me = CompanyDetail::current();
        $prefix = (string) ($me->invoice_prefix ?? '{year}-');
        $next = str_contains($prefix, '{year}') && $me->invoice_year && (int) $me->invoice_year !== (int) now()->year ? 1 : (int) $me->invoice_next;

        return str_replace('{year}', (string) now()->year, $prefix).str_pad((string) $next, 4, '0', STR_PAD_LEFT);
    }

    /** What would stop the e-invoice; empty when it can go. */
    public static function problems(PreInvoice $pf): array
    {
        $p = [];
        $me = CompanyDetail::current();
        if (! $me->vat_number) {
            $p[] = __('finance.okioki.no_own_vat');
        }
        $pf->loadMissing(['company', 'member']);
        if (! $pf->company && ! $pf->member) {
            $p[] = __('finance.okioki.no_customer');
        } elseif ($pf->company && ! $pf->company->vat_number && ! $pf->company->email) {
            $p[] = __('finance.okioki.no_vat_or_email');
        } elseif (! $pf->company && ! $pf->member?->email) {
            $p[] = __('finance.okioki.no_vat_or_email');
        }
        if ($pf->net_cents <= 0) {
            $p[] = __('finance.okioki.not_positive');
        }

        return $p;
    }

    /** Makes the e-invoice, keeps it, and mails it to OkiOki. True when it went out. */
    public static function send(PreInvoice $pf, ?User $by): bool
    {
        try {
            $xml = UblInvoice::make($pf);
            $path = 'private/invoices/'.$pf->invoice_date->format('Y').'/'.preg_replace('/[^A-Za-z0-9._-]/', '_', $pf->invoice_number).'.xml';
            Storage::disk('local')->put($path, $xml);
            $pf->forceFill(['ubl_path' => $path])->save();
        } catch (Throwable $e) {
            $pf->forceFill(['okioki_error' => mb_substr('E-invoice: '.$e->getMessage(), 0, 500)])->save();

            return false;
        }
        $me = CompanyDetail::current();
        $mail = new Confirmation(
            subject: 'Invoice '.$pf->invoice_number.' · '.$pf->customerName(),
            greeting: 'OkiOki',
            intro: ['E-invoice (Peppol BIS Billing 3.0) '.$pf->invoice_number.' for '.$pf->customerName().', '.number_format($pf->gross_cents / 100, 2, ',', '.').' EUR incl. VAT.'],
            team: 'CLUK app',
        );
        $mail->withoutBanner()->attachFiles = [['path' => Storage::disk('local')->path($path), 'name' => basename($path), 'mime' => 'application/xml']];
        $ok = SafeMail::notify(Notification::route('mail', $me->okioki_inbox), $mail);
        $pf->forceFill($ok ? ['okioki_sent_at' => now(), 'okioki_error' => null] : ['okioki_error' => __('finance.okioki.mail_failed')])->save();
        AuditLog::record($ok ? 'finance.okioki_sent' : 'finance.okioki_failed', $pf, ['invoice' => $pf->invoice_number, 'to' => $me->okioki_inbox], $by);

        return $ok;
    }
}
