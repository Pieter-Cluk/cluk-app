<?php

namespace App\Services\Finance;

use App\Models\Afrit6\Statement;
use App\Models\AuditLog;
use App\Models\Company;
use App\Models\CompanyDetail;
use App\Models\Factory\Booking;
use App\Models\Finance\PreInvoice;
use App\Models\Finance\Reminder;
use App\Models\ProductSales\SalesQuote;
use App\Models\Rental\Order;
use App\Models\Rental\SubhireRequest;
use App\Models\User;
use App\Models\VatRate;
use App\Models\Warehouse\Charge;
use App\Notifications\Confirmation;
use App\Services\Afrit6\Pricing;
use App\Support\StaffMail;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Pre-invoicing: every proforma passes the accountant. Automatic checks flag what
 * could be wrong (company data, VAT, PO, margin, amounts, customer); red flags block
 * unless the accountant accepts them by hand. Then OkiOki makes the invoice.
 */
class PreInvoices
{
    public const TYPES = ['rental', 'charges', 'factory', 'sales', 'afrit6'];

    /** Make (or refresh) the pre-invoice of a source document. */
    public static function make(string $type, Model $src, ?User $by): PreInvoice
    {
        $data = self::build($type, $src);
        $existing = PreInvoice::where('source_type', $type)->where('source_id', $src->getKey())->where('status', '!=', 'cancelled')->first();
        if ($existing && in_array($existing->status, ['sent', 'paid'], true)) {
            return $existing;
        }

        return DB::transaction(function () use ($existing, $data, $type, $src, $by) {
            if ($existing) {
                $existing->update($data + ['status' => 'check']);
                $pf = $existing;
            } else {
                $pf = PreInvoice::create($data + ['number' => PreInvoice::nextNumber(), 'source_type' => $type, 'source_id' => $src->getKey(), 'made_by_id' => $by?->id, 'status' => 'check']);
                AuditLog::record('finance.pf_made', $pf, ['source' => $pf->source_code, 'total' => Pricing::eur($pf->gross_cents)], $by);
            }
            $pf->update(['checks' => self::checks($pf->fresh(['company', 'member']))]);

            return $pf->fresh();
        });
    }

    /** @return array<string, mixed> */
    private static function build(string $type, Model $src): array
    {
        $line = fn (string $d, $q, int $unit, int $amount, ?string $note = null) => ['d' => $d, 'q' => (string) $q, 'unit_cents' => $unit, 'amount_cents' => $amount, 'note' => $note];
        $me = CompanyDetail::current();
        $belgian = fn () => VatRate::forCode((string) $me->vat_default_be, today()->toDateString());

        [$company, $member, $owner, $module, $desc, $lines, $cost, $po, $date, $code, $rate] = match ($type) {
            'rental' => (function () use ($src, $line) {
                /** @var Order $src */
                $src->loadMissing(['lines', 'company', 'quote', 'subhires.lines']);
                $cost = (int) $src->subhires->where('status', 'accepted')->sum(fn (SubhireRequest $r) => $r->lines->sum('amount_cents'));

                $lines = $src->lines->map(fn ($l) => $line($l->name.($l->kind === 'item' && (float) $l->factor !== 1.0 ? ' · factor '.rtrim(rtrim(number_format((float) $l->factor, 2, '.', ''), '0'), '.') : ''), $l->qtyLabel(), (int) $l->unit_price_cents, (int) $l->amount_cents))->values();
                if ($src->discount_cents) {
                    $lines->push($line(__('quotes.project_discount', [], $src->language).' '.rtrim(rtrim(number_format($src->project_discount_bp / 100, 2, '.', ''), '0'), '.').'%', 1, -(int) $src->discount_cents, -(int) $src->discount_cents));
                }
                if ($t = $src->transportCharged()) {
                    $lines->push($line(__('quotes.transport.transport_line', [], $src->language).($src->transport_note ? ' · '.$src->transport_note : ''), 1, $t, $t));
                }

                return [$src->company, null, $src->handedBy, 'dryhire', $src->title.' · '.$src->use_from->format('d/m').'–'.$src->use_until->format('d/m'),
                    $lines->all(),
                    $cost, $src->po_number, $src->use_from, str_replace('R-OC', 'R-PF', $src->number), $src->company?->vatRate($src->use_from->toDateString())];
            })(),
            'charges' => (function () use ($src, $line) {
                /** @var Order $src */
                $ch = Charge::where('order_id', $src->id)->where('status', 'approved')->get();

                return [$src->company, null, $src->handedBy, 'dryhire', $src->title.' · '.__('finance.charges_desc'),
                    $ch->map(fn ($c) => $line($c->label, $c->qty, (int) round($c->amount_cents / max(1, $c->qty)), (int) $c->amount_cents))->all(),
                    0, $src->po_number, $src->returned_at ?? today(), str_replace('R-OC', 'R-CH', $src->number), $src->company?->vatRate()];
            })(),
            'factory' => (function () use ($src, $line, $belgian) {
                /** @var Booking $src */
                $q = $src->currentQuote()?->load('lines');

                return [$src->company, null, $src->owner, 'factory', $src->title.' · '.$src->start_date->format('d/m/Y'),
                    collect($q?->lines ?? [])->map(fn ($l) => $line($l->name, $l->qtyLabel(), (int) $l->unit_price_cents, (int) $l->amount_cents))->all(),
                    0, $q?->po_number, $src->start_date, 'F-PF-'.Str::after($src->number, 'F-'), $belgian()];
            })(),
            'sales' => (function () use ($src, $line) {
                /** @var SalesQuote $src */
                $src->loadMissing(['lines', 'company']);

                return [$src->company, null, $src->owner, 'sales', $src->title,
                    $src->lines->where('kind', '!=', 'text')->map(fn ($l) => $line($l->name, $l->qty, (int) $l->unit_cents, (int) $l->amount_cents))->values()->all(),
                    (int) $src->cost_cents, $src->po_number, $src->delivered_at ?? today(), $src->docCode('S-PF'), $src->company?->vatRate()];
            })(),
            'afrit6' => (function () use ($src, $line, $belgian) {
                /** @var Statement $src */
                $src->loadMissing(['member.company']);
                $m = $src->member;

                return [$m?->company, $m, null, 'afrit6', __('finance.statement_desc', ['from' => $src->period_start->format('d/m'), 'to' => $src->period_end->format('d/m/Y')]),
                    collect($src->lines)->map(fn ($l) => $line($l['text'], rtrim(rtrim(number_format((float) $l['qty'], 2, '.', ''), '0'), '.'), (int) $l['unit_cents'], (int) $l['amount_cents']))->all(),
                    0, null, $src->period_start, 'A6S-'.$src->period_start->format('Y-m').'-'.$src->member_id, $belgian()];
            })(),
        };
        $rate ??= $belgian();
        $net = (int) collect($lines)->sum('amount_cents');
        $vat = (int) round($net * ($rate?->rate_bp ?? 0) / 10000);

        return [
            'module' => $module, 'company_id' => $company?->id, 'member_id' => $member?->id, 'owner_id' => $owner?->id, 'description' => mb_substr($desc, 0, 200),
            'lines' => $lines, 'vat_code' => $rate?->code, 'vat_rate_bp' => $rate?->rate_bp ?? 0, 'net_cents' => $net, 'cost_cents' => $cost, 'vat_cents' => $vat, 'gross_cents' => $net + $vat,
            'po_number' => $po, 'payment_days' => (int) ($company?->customer_payment_days ?? $me->payment_term_days ?? 0), 'service_date' => $date, 'source_code' => $code,
        ];
    }

    /** The automatic checks: [level ok|warn|bad, category, key, params]. */
    public static function checks(PreInvoice $pf): array
    {
        $c = [];
        $add = function (string $level, string $cat, string $key, array $p = []) use (&$c) {
            $c[] = ['level' => $level, 'cat' => $cat, 'key' => $key, 'p' => $p];
        };
        $co = $pf->company;
        if (! $co) {
            $pf->member ? $add('ok', 'company', 'private_member') : $add('bad', 'company', 'no_company');
        } else {
            if (! $co->vat_number) {
                $co->country === 'BE' ? $add('warn', 'company', 'no_vat_be') : $add('bad', 'company', 'no_vat');
            } elseif ($co->vies_status === 'valid') {
                $add('ok', 'company', 'vies_valid');
            } elseif ($co->vies_status === 'invalid') {
                $add('bad', 'company', 'vies_invalid', ['vat' => $co->vat_number]);
            } else {
                $add('warn', 'company', 'vies_unknown', ['vat' => $co->vat_number]);
            }
            $co->street && $co->city ? $add('ok', 'company', 'address_ok') : $add('bad', 'company', 'address_missing');
            if ($co->po_required) {
                $pf->po_number ? $add('ok', 'company', 'po_ok', ['po' => $pf->po_number]) : $add('bad', 'company', 'po_missing');
            }
            // VAT: the rate must fit the customer (the venue and Afrit 6 are always Belgian VAT).
            $expected = in_array($pf->module, ['factory', 'afrit6'], true) ? (string) CompanyDetail::current()->vat_default_be : $co->vatCode();
            $pf->vat_code === $expected ? $add('ok', 'vat', 'vat_fits', ['code' => $pf->vat_code, 'pct' => $pf->vat_rate_bp / 100]) : $add('bad', 'vat', 'vat_wrong', ['code' => (string) $pf->vat_code, 'expected' => $expected]);
            // Customer: unpaid invoices past their due date.
            $late = PreInvoice::where('company_id', $co->id)->where('id', '!=', $pf->id)->where('status', 'sent')->whereDate('due_date', '<', today())->get();
            $late->isEmpty() ? $add('ok', 'customer', 'no_overdue') : $add('warn', 'customer', 'overdue', ['n' => $late->count(), 'amount' => Pricing::eur((int) $late->sum('gross_cents'))]);
        }
        if ($pf->net_cents <= 0) {
            $add('bad', 'amounts', 'zero');
        }
        // Amounts against the accepted quote, and the margin.
        if ($pf->source_type === 'rental' && ($o = Order::with('quote')->find($pf->source_id)) && $o->quote) {
            // Transport added or changed after the quote was won is expected: compare without it.
            $extra = $o->transportCharged() - $o->quote->transportCharged();
            $o->quote->net_cents + $extra === $pf->net_cents
                ? $add('ok', 'amounts', $extra ? 'matches_quote_transport' : 'matches_quote', ['quote' => $o->quote->code(), 'transport' => Pricing::eur($extra)])
                : $add('warn', 'amounts', 'differs_quote', ['quote' => $o->quote->code(), 'diff' => Pricing::eur($pf->net_cents - $o->quote->net_cents - $extra)]);
            if ($o->transportToPrice()) {
                $add('warn', 'amounts', 'transport_missing');
            }
        }
        if ($pf->source_type === 'sales' && ($q = SalesQuote::with('lines')->find($pf->source_id))) {
            $low = $q->lowLines();
            if ($low->isNotEmpty() && $q->approved_sig !== $q->signature()) {
                $add('bad', 'margin', 'low_no_approval', ['lines' => $low->pluck('name')->implode(', ')]);
            } elseif ($low->isNotEmpty()) {
                $add('warn', 'margin', 'low_approved', ['name' => $q->approvedBy?->name ?? '—']);
            } else {
                $add('ok', 'margin', 'margin_ok', ['pct' => number_format($q->marginBp() / 100, 1, ',', '')]);
            }
        }
        if ($pf->cost_cents > 0 && $pf->net_cents > 0) {
            $m = ($pf->net_cents - $pf->cost_cents) / $pf->net_cents;
            $m < 0.1 ? $add('warn', 'margin', 'cost_margin', ['pct' => number_format($m * 100, 1, ',', ''), 'cost' => Pricing::eur($pf->cost_cents)]) : $add('ok', 'margin', 'cost_margin_ok', ['pct' => number_format($m * 100, 1, ',', '')]);
        }

        return $c;
    }

    public static function accept(PreInvoice $pf, User $by, ?string $invoiceNumber, bool $override): void
    {
        abort_unless($pf->status === 'check', 409);
        $pf->update(['checks' => self::checks($pf->load(['company', 'member']))]);
        if ($pf->reds() > 0 && ! $override) {
            throw ValidationException::withMessages(['override' => __('finance.errors.red_flags')]);
        }
        $pf->update(['status' => 'sent', 'sent_at' => now(), 'sent_by_id' => $by->id, 'override_by_id' => $pf->reds() ? $by->id : null,
            'invoice_number' => $invoiceNumber ?: null, 'invoice_date' => today(), 'due_date' => today()->addDays($pf->payment_days)]);
        AuditLog::record('finance.pf_sent', $pf, ['invoice' => $invoiceNumber, 'override' => $pf->reds() ? $pf->reds() : null, 'total' => Pricing::eur($pf->gross_cents)], $by);
    }

    public static function back(PreInvoice $pf, User $by, string $reason, string $note): void
    {
        abort_unless($pf->status === 'check', 409);
        $pf->update(['status' => 'back', 'back_note' => __('finance.reasons.'.$reason).' · '.$note]);
        AuditLog::record('finance.pf_back', $pf, ['reason' => __('finance.reasons.'.$reason), 'note' => $note], $by);
        if ($pf->madeBy && ! $pf->madeBy->is($by)) {
            StaffMail::send($pf->madeBy, 'pf_back', (new Confirmation(
                subject: __('finance.mail.back_subject', ['number' => $pf->number], $pf->madeBy->locale ?: 'en'),
                greeting: __('mails.hello', ['name' => $pf->madeBy->first_name], $pf->madeBy->locale ?: 'en'),
                intro: [__('finance.mail.back_intro', ['number' => $pf->number, 'customer' => $pf->customerName()], $pf->madeBy->locale ?: 'en'), $pf->back_note],
                button: [__('finance.mail.back_button', [], $pf->madeBy->locale ?: 'en'), route('finance.index', ['pf' => $pf->id])],
                signer: $by,
            ))->locale($pf->madeBy->locale ?: 'en'));
        }
    }

    /** The maker fixed it: rebuilt from the source and back to the accountant. */
    public static function resend(PreInvoice $pf, User $by, ?string $reply): PreInvoice
    {
        abort_unless($pf->status === 'back', 409);
        $src = self::source($pf);
        $new = $src ? self::make($pf->source_type, $src, $by) : $pf;
        $new->update(['status' => 'check']);
        AuditLog::record('finance.pf_resent', $new, ['reply' => $reply], $by);

        return $new;
    }

    public static function paid(PreInvoice $pf, User $by): void
    {
        abort_unless($pf->status === 'sent', 409);
        $pf->update(['status' => 'paid', 'paid_at' => now()]);
        AuditLog::record('finance.pf_paid', $pf, [], $by);
    }

    public static function source(PreInvoice $pf): ?Model
    {
        return match ($pf->source_type) {
            'rental', 'charges' => Order::find($pf->source_id),
            'factory' => Booking::find($pf->source_id),
            'sales' => SalesQuote::find($pf->source_id),
            'afrit6' => Statement::find($pf->source_id),
            default => null,
        };
    }

    /** Late payment: interest at the statutory rate (simple, on the invoice amount) and the fixed compensation. */
    public static function lateFee(PreInvoice $pf, ?Carbon $until = null): array
    {
        $days = $pf->due_date ? max(0, (int) $pf->due_date->diffInDays($until ?? today(), false)) : 0;
        $interest = (int) round($pf->gross_cents * (float) config('afrit6.payment.statutory_rate') / 100 * $days / 365);

        return ['days' => $days, 'interest' => $interest, 'fixed' => (int) config('afrit6.payment.fixed_compensation')];
    }

    public static function decide(PreInvoice $pf, string $decision, ?string $reason, User $by): Reminder
    {
        $fee = self::lateFee($pf);
        $r = Reminder::updateOrCreate(['pre_invoice_id' => $pf->id], ['decision' => $decision, 'reason' => $reason, 'interest_cents' => $fee['interest'], 'fixed_cents' => $fee['fixed'],
            'decided_by_id' => $by->id, 'decided_at' => now()]);
        AuditLog::record('finance.late_'.$decision, $pf, ['days' => $fee['days'], 'interest' => Pricing::eur($fee['interest']), 'fixed' => Pricing::eur($fee['fixed']), 'reason' => $reason], $by);

        return $r;
    }

    /** Is this customer a private person (consumer rules for late payment)? */
    public static function isPrivate(PreInvoice $pf): bool
    {
        return ! $pf->company || ! $pf->company->vat_number;
    }
}
