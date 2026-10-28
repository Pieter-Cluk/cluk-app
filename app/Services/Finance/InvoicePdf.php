<?php

namespace App\Services\Finance;

use App\Models\BankAccount;
use App\Models\CompanyDetail;
use App\Models\Finance\PreInvoice;
use App\Services\Finance\OkiOki\UblInvoice;
use App\Support\InLocale;
use App\Support\VatNumber;
use Dompdf\Dompdf;
use Dompdf\Options;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

/**
 * The printable invoice (PDF), in the customer's language: sent inside the e-invoice and
 * as an attachment to OkiOki, downloadable by staff and by the customer in My CLUK.
 */
class InvoicePdf
{
    public static function make(PreInvoice $pf): string
    {
        if (! $pf->invoice_number || ! $pf->invoice_date) {
            throw new RuntimeException('The invoice has no number or date yet.');
        }
        $pf->loadMissing(['company', 'member.company']);
        $loc = self::locale($pf);
        $me = CompanyDetail::current();
        $bank = BankAccount::where('on_documents', true)->orderByDesc('is_main')->first();
        $ogm = \App\Support\PaymentQr::ogm($pf->invoice_number);
        $payload = $bank && $pf->status !== 'paid' ? \App\Support\PaymentQr::payload(trim(($me->legal_name ?: 'CLUK').' '.($me->legal_form ?? '')), (string) $bank->iban, $bank->bic, (int) $pf->gross_cents, $ogm) : null;
        $html = InLocale::run($loc, fn () => view('finance.invoice-pdf', [
            'pf' => $pf, 'me' => $me, 'buyer' => UblInvoice::buyerOf($pf), 'loc' => $loc,
            'bank' => $bank, 'ogm' => $ogm, 'qr' => $payload ? \App\Support\PaymentQr::png($payload) : null,
            'exempt' => UblInvoice::category($pf)[1][1] ?? null,
            'logo' => is_file($f = public_path('images/cluk-logo.png')) ? 'data:image/png;base64,'.base64_encode((string) file_get_contents($f)) : null,
            'vatFmt' => fn (?string $v) => $v ? VatNumber::format($v) : null,
        ])->render());

        $o = new Options;
        $o->set('isRemoteEnabled', false);
        $o->set('defaultFont', 'DejaVu Sans');
        $o->setChroot(public_path());
        $o->set('tempDir', storage_path('framework/cache'));
        $o->set('fontCache', storage_path('framework/cache'));
        $pdf = new Dompdf($o);
        $pdf->loadHtml($html, 'UTF-8');
        $pdf->setPaper('A4');
        $pdf->render();

        return (string) $pdf->output();
    }

    /** Made once and kept with the invoice (same file every time it is opened). */
    public static function stored(PreInvoice $pf): string
    {
        $path = 'private/invoices/'.$pf->invoice_date->format('Y').'/'.self::fileName($pf);
        if (! Storage::disk('local')->exists($path)) {
            Storage::disk('local')->put($path, self::make($pf));
        }

        return $path;
    }

    public static function fileName(PreInvoice $pf): string
    {
        return preg_replace('/[^A-Za-z0-9._-]/', '_', (string) $pf->invoice_number).'.pdf';
    }

    public static function locale(PreInvoice $pf): string
    {
        $l = $pf->company?->language ?: $pf->member?->locale ?: 'en';

        return array_key_exists($l, config('cluk.locales')) ? $l : 'en';
    }
}
